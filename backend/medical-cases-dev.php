<?php
/** Local fictional database test only. This is not an authentication provider or API. */
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Direct access is unavailable.');
}

function mcDevAllowed(): bool
{
    // Fail closed on Apache/production, remote clients, and DNS-rebinding Host headers.
    return PHP_SAPI === 'cli-server' && getenv('CHN_CASE_DEV_MODE') === '1'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        && preg_match('/\A(?:127\.0\.0\.1|localhost|\[::1\])(?::[0-9]{1,5})?\z/D', $_SERVER['HTTP_HOST'] ?? '') === 1;
}

function mcDevConnect(bool $setup = false): PDO
{
    // Never accept database names, credentials or connection details from a request.
    $socket = getenv('CHN_CASE_DEV_DB_SOCKET') ?: '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
    return new PDO('mysql:unix_socket=' . $socket . ($setup ? '' : ';dbname=chn_case_creation_dev') . ';charset=utf8mb4',
        getenv('CHN_CASE_DEV_DB_USER') ?: 'root', getenv('CHN_CASE_DEV_DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => $setup,
        ]);
}

final class McCaseDevStore
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; $this->actor(); }

    private function actor(): array
    {
        // Server-fixed development actor, independent of URL, POST and cookies.
        $statement = $this->db->prepare('SELECT d.doctor_id, d.department_id FROM doctor d JOIN `user` u ON u.user_id=d.user_id JOIN department dp ON dp.department_id=d.department_id WHERE d.doctor_id=? AND u.role=? AND u.email=? AND dp.hospital_id IS NOT NULL');
        $statement->execute([601, 'doctor', 'case-dev-doctor@example.invalid']);
        $actor = $statement->fetch();
        if (!$actor) throw new RuntimeException('Development doctor is unavailable.');
        return $actor;
    }

    public function options(): array
    {
        $actor = $this->actor();
        $patients = $this->db->query("SELECT p.patient_id, CONCAT(u.name, ' ', u.surname) patient_name FROM patient p JOIN `user` u ON u.user_id=p.user_id WHERE u.role='patient' AND ((p.patient_id=501 AND u.email='case-dev-patient-a@example.invalid') OR (p.patient_id=502 AND u.email='case-dev-patient-b@example.invalid')) ORDER BY p.patient_id")->fetchAll();
        $statement = $this->db->prepare('SELECT department_id, department_name FROM department WHERE department_id=? AND hospital_id IS NOT NULL');
        $statement->execute([$actor['department_id']]);
        $departments = $statement->fetchAll();
        $statement = $this->db->prepare("SELECT d.doctor_id, CONCAT(u.name, ' ', u.surname) doctor_name FROM doctor d JOIN `user` u ON u.user_id=d.user_id WHERE d.doctor_id=? AND d.department_id=? AND u.role='doctor'");
        $statement->execute([$actor['doctor_id'], $actor['department_id']]);
        return ['patients' => $patients, 'departments' => $departments, 'doctors' => $statement->fetchAll()];
    }

    public function patientIdentity(): array
    {
        // Fixed fictional identity on the server; never taken from URL, POST or a role cookie.
        $statement = $this->db->prepare("SELECT p.patient_id,p.user_id,CONCAT(u.name,' ',u.surname) patient_name FROM patient p JOIN `user` u ON u.user_id=p.user_id WHERE p.patient_id=? AND u.role='patient' AND u.email=?");
        $statement->execute([501, 'case-dev-patient-a@example.invalid']);
        $patient = $statement->fetch();
        if (!$patient) throw new RuntimeException('Development patient is unavailable.');
        return $patient;
    }

    public function patientDraft(array $input): array
    {
        $patient = $this->patientIdentity();
        $actor = $this->actor();
        $errors = [];
        $department = $input['department_id'] ?? '';
        if (!is_string($department) || $department !== (string) $actor['department_id']) $errors['department_id'] = 'Select the available department for clinical review.';
        $description = $input['case_description'] ?? '';
        if (!is_string($description) || !mb_check_encoding($description, 'UTF-8') || strlen($description) > 65535) $errors['case_description'] = 'Complaint must be valid text of at most 65,535 UTF-8 bytes.';
        elseif (trim($description) === '') $errors['case_description'] = 'Please describe your complaint.';
        if ($errors) return ['errors' => $errors];
        return ['draft' => ['patient_id' => (int) $patient['patient_id'], 'department_id' => (int) $actor['department_id'], 'case_description' => $description]];
    }

    public function reviewPatientDraft(array $draft, $triage): array
    {
        if (!mcDevAllowed()) throw new RuntimeException('Local review capability required.');
        $validated = $this->patientDraft(['department_id' => (string) $draft['department_id'], 'case_description' => $draft['case_description']]);
        if (isset($validated['errors']) || $draft['patient_id'] !== (int) $this->patientIdentity()['patient_id']) return ['errors' => ['review' => 'The complaint is no longer available for review.']];
        if (!is_string($triage) || !in_array($triage, ['green', 'yellow', 'orange'], true)) return ['errors' => ['review' => 'The clinician must select a valid triage classification.']];
        return ['review' => ['draft_id' => $draft['id'], 'patient_id' => $draft['patient_id'], 'department_id' => $draft['department_id'],
            'description_hash' => hash('sha256', $draft['case_description']), 'tier_color' => $triage,
            'doctor_id' => (int) $this->actor()['doctor_id'], 'expires' => time() + 1800]];
    }

    public function createPatient(array $input, ?array $draft): array
    {
        if (!mcDevAllowed()) throw new RuntimeException('Local write capability required.');
        $validated = $this->patientDraft($input);
        if (isset($validated['errors'])) return $validated;
        $review = $draft['review'] ?? null;
        $values = $validated['draft'];
        if (!$review || $review['expires'] < time() || $review['draft_id'] !== $draft['id']
            || $review['patient_id'] !== $values['patient_id'] || $review['department_id'] !== $values['department_id']
            || $review['doctor_id'] !== (int) $this->actor()['doctor_id']
            || !hash_equals($review['description_hash'], hash('sha256', $values['case_description']))) {
            return ['errors' => ['review' => 'A clinician must review this exact complaint before the case can be saved. Request a new review if you changed it.']];
        }
        // Only server-held identity and clinician-held classification reach the insert.
        return $this->create(['patient_id' => (string) $values['patient_id'], 'department_id' => (string) $values['department_id'],
            'doctor_id' => (string) $review['doctor_id'], 'case_description' => $values['case_description'], 'tier_color' => $review['tier_color']]);
    }

    public function records(string $role): array
    {
        $actor = $this->actor();
        $patient = $this->patientIdentity();
        // URL scopes apply ONLY to this dedicated fictional database, never real records.
        $scope = [
            'patient' => ['c.patient_id=?', [$patient['patient_id']]],
            'doctor' => ['c.department_id=? AND (c.doctor_id=? OR c.doctor_id IS NULL)', [$actor['department_id'], $actor['doctor_id']]],
            'staff' => ['c.department_id=?', [$actor['department_id']]],
            'admin' => ['1=0', []],
        ][$role];
        $description = $role === 'staff' ? 'NULL' : 'c.case_description';
        $statement = $this->db->prepare("SELECT c.case_id,c.patient_id,c.department_id,c.doctor_id,c.case_state,c.tier_color,c.created_at, $description case_description, dp.department_name, CONCAT(pu.name,' ',pu.surname) patient_name, CONCAT(du.name,' ',du.surname) doctor_name FROM `case` c JOIN department dp ON dp.department_id=c.department_id JOIN patient p ON p.patient_id=c.patient_id JOIN `user` pu ON pu.user_id=p.user_id LEFT JOIN doctor d ON d.doctor_id=c.doctor_id LEFT JOIN `user` du ON du.user_id=d.user_id WHERE {$scope[0]} ORDER BY c.created_at DESC,c.case_id DESC");
        $statement->execute($scope[1]);
        $cases = $statement->fetchAll();
        $diagnoses = [];
        if ($cases && in_array($role, ['patient', 'doctor'], true)) {
            $ids = array_column($cases, 'case_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $statement = $this->db->prepare("SELECT dg.*,CONCAT(u.name,' ',u.surname) doctor_name FROM diagnosis dg LEFT JOIN doctor d ON d.doctor_id=dg.doctor_id LEFT JOIN `user` u ON u.user_id=d.user_id WHERE dg.case_id IN ($placeholders) ORDER BY dg.diagnosed_at DESC,dg.diagnosis_id DESC");
            $statement->execute($ids);
            $diagnoses = $statement->fetchAll();
        }
        return ['cases' => $cases, 'diagnoses' => $diagnoses];
    }

    /** Return field errors or the actual database-generated case ID. */
    public function create(array $input): array
    {
        if (!mcDevAllowed()) throw new RuntimeException('Local write capability required.');
        $errors = [];
        $ids = [];
        foreach (['patient_id', 'department_id', 'doctor_id'] as $field) {
            $raw = $input[$field] ?? '';
            if ($field === 'doctor_id' && $raw === '') { $ids[$field] = null; continue; }
            $id = is_string($raw) && preg_match('/\A[1-9][0-9]{0,9}\z/D', $raw)
                ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) : false;
            if ($id === false) $errors[$field] = 'Select a valid ' . str_replace('_id', '', $field) . '.';
            $ids[$field] = $id;
        }
        $triage = $input['tier_color'] ?? '';
        if (!is_string($triage) || !in_array($triage, ['green', 'yellow', 'orange'], true)) $errors['tier_color'] = 'Select green, yellow or orange triage.';
        $description = $input['case_description'] ?? '';
        if (!is_string($description) || !mb_check_encoding($description, 'UTF-8') || strlen($description) > 65535) $errors['case_description'] = 'Description must be valid text of at most 65,535 UTF-8 bytes.';
        if ($errors) return ['errors' => $errors];

        $this->db->beginTransaction();
        try {
            $actor = $this->actor();
            $options = $this->options();
            if (!in_array($ids['patient_id'], array_column($options['patients'], 'patient_id'))) $errors['patient_id'] = 'Select an available fictional patient.';
            if ($ids['department_id'] !== (int) $actor['department_id']) $errors['department_id'] = 'Only the development doctor’s assigned department is permitted.';
            if ($ids['doctor_id'] !== null) {
                $statement = $this->db->prepare('SELECT department_id FROM doctor WHERE doctor_id=? FOR UPDATE');
                $statement->execute([$ids['doctor_id']]);
                $department = $statement->fetchColumn();
                if ($department === false || (int) $department !== $ids['department_id']) $errors['doctor_id'] = 'The responsible doctor must belong to the selected department.';
                elseif ($ids['doctor_id'] !== (int) $actor['doctor_id']) $errors['doctor_id'] = 'Only the fixed development doctor may be assigned in this test.';
            }
            if ($errors) { $this->db->rollBack(); return ['errors' => $errors]; }
            // IDs/timestamp generated by MySQL; case_state is never accepted from the browser.
            $statement = $this->db->prepare("INSERT INTO `case` (patient_id,department_id,doctor_id,case_description,tier_color,case_state) VALUES (?,?,?,?,?,'open')");
            $statement->execute([$ids['patient_id'], $ids['department_id'], $ids['doctor_id'], trim($description) === '' ? null : $description, $triage]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return ['case_id' => $id];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}

/** Create purpose-bound CSRF form nonces; this is not role/session authentication. */
function mcDevFormToken(string $purpose, string $draftId = ''): string
{
    while (count($_SESSION['mc_forms']) >= 20) array_shift($_SESSION['mc_forms']);
    $token = bin2hex(random_bytes(32));
    $_SESSION['mc_forms'][$token] = ['time' => time(), 'case_id' => null, 'purpose' => $purpose, 'draft_id' => $draftId];
    return $token;
}

/** Request orchestration for the shared page. Sessions hold tokens and fictional drafts, never active roles. */
function mcDevPage(string $uiRole): array
{
    $page = ['enabled' => mcDevAllowed(), 'store' => null, 'options' => ['patients' => [], 'departments' => [], 'doctors' => []],
        'errors' => [], 'message' => '', 'notice' => '', 'success' => '', 'csrf' => '', 'submission' => '',
        'review_submission' => '', 'patient_draft' => null, 'review_ready' => false,
        'values' => ['patient_id' => '', 'department_id' => '', 'doctor_id' => '601', 'case_description' => '', 'tier_color' => '']];
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if (!$page['enabled']) {
        if ($post) { http_response_code(403); $page['message'] = 'Case creation is disabled. URL roles do not authorize database writes.'; }
        return $page;
    }
    header('Cache-Control: no-store');
    try {
        $page['store'] = new McCaseDevStore(mcDevConnect());
        $page['options'] = $page['store']->options();
        $page['store']->patientIdentity();
        $page['values']['department_id'] = (string) ($page['options']['departments'][0]['department_id'] ?? '');
        if (session_status() !== PHP_SESSION_NONE) throw new RuntimeException('Separate development CSRF session required.');
        session_name('CHN_CASE_DEV');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'path' => '/']);
        if (!session_start()) throw new RuntimeException('CSRF session unavailable.');
        $_SESSION['mc_csrf'] ??= bin2hex(random_bytes(32));
        $_SESSION['mc_forms'] ??= [];
        foreach ($_SESSION['mc_forms'] as $token => $form) {
            if ($form['time'] < time() - 1800) unset($_SESSION['mc_forms'][$token]);
        }
        if (isset($_SESSION['mc_patient_draft']) && $_SESSION['mc_patient_draft']['time'] < time() - 1800) unset($_SESSION['mc_patient_draft']);
        $page['csrf'] = $_SESSION['mc_csrf'];
        if ($uiRole === 'patient' && isset($_SESSION['mc_patient_draft'])) {
            $page['values']['department_id'] = (string) $_SESSION['mc_patient_draft']['department_id'];
            $page['values']['case_description'] = $_SESSION['mc_patient_draft']['case_description'];
        }
        if ($post) {
            // URL role restricts the UI action further, but only the local server gate grants test capability.
            $action = $_POST['action'] ?? ($uiRole === 'doctor' ? 'create_doctor' : 'patient_request');
            $allowed = ['patient' => ['patient_request', 'patient_create'], 'doctor' => ['create_doctor', 'patient_review'], 'staff' => [], 'admin' => []][$uiRole];
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $csrf = $_POST['csrf_token'] ?? '';
            $forbiddenPatientFields = $uiRole === 'patient' && (array_key_exists('patient_id', $_POST) || array_key_exists('user_id', $_POST) || array_key_exists('doctor_id', $_POST) || array_key_exists('tier_color', $_POST));
            if (!is_string($action) || !in_array($action, $allowed, true) || $forbiddenPatientFields
                || ($origin !== '' && $origin !== 'http://' . $_SERVER['HTTP_HOST'])
                || !is_string($csrf) || !hash_equals($page['csrf'], $csrf)) {
                http_response_code(403);
                $page['message'] = 'Creation was rejected. Use the correct local form and security token. Patient identity, doctor assignment and clinical triage cannot be supplied by a patient.';
            } else {
                foreach ($page['values'] as $key => $value) {
                    $page['values'][$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
                }
                $nonce = $_POST['submission_token'] ?? '';
                if (!is_string($nonce) || !preg_match('/\A[a-f0-9]{64}\z/D', $nonce) || !isset($_SESSION['mc_forms'][$nonce])) {
                    http_response_code(409);
                    $page['message'] = 'This form expired or is no longer valid. Review the values and use the refreshed form.';
                } elseif (($_SESSION['mc_forms'][$nonce]['purpose'] ?? '') !== $action) {
                    http_response_code(403);
                    $page['message'] = 'This form token cannot be used for another action.';
                } else {
                    if ($action === 'patient_review') $page['review_submission'] = $nonce;
                    else $page['submission'] = $nonce;
                    $saved = $_SESSION['mc_forms'][$nonce]['case_id'] ?? null;
                    $result = [];
                    $draft = $_SESSION['mc_patient_draft'] ?? null;
                    if ($saved === null && !empty($_SESSION['mc_forms'][$nonce]['done'])) {
                        // A repeated draft request or review must not erase an existing approval.
                        session_write_close();
                        header('Location: ' . chnPrototypeUrl('medical-cases.php#create-case', $uiRole), true, 303);
                        exit;
                    }
                    if ($saved === null) {
                        if ($action === 'patient_request') {
                            $result = $page['store']->patientDraft($_POST);
                            if (isset($result['draft'])) {
                                $_SESSION['mc_patient_draft'] = $result['draft'] + ['id' => bin2hex(random_bytes(16)), 'time' => time()];
                                $_SESSION['mc_forms'][$nonce]['done'] = true;
                                $_SESSION['mc_intake_notice'] = 'Complaint held in this local test session for clinical review. No case has been saved to MySQL. Switch the demo role to Doctor to review this fictional complaint, then return as Patient.';
                            }
                        } elseif ($action === 'patient_review') {
                            if (!$draft || $_SESSION['mc_forms'][$nonce]['draft_id'] !== $draft['id']) {
                                http_response_code(409);
                                $result = ['errors' => ['review' => 'The complaint changed or expired. Reload it before clinical review.']];
                            } else {
                                $result = $page['store']->reviewPatientDraft($draft, $_POST['tier_color'] ?? '');
                                if (isset($result['review'])) {
                                    $_SESSION['mc_patient_draft']['review'] = $result['review'];
                                    $_SESSION['mc_forms'][$nonce]['done'] = true;
                                    $_SESSION['mc_intake_notice'] = 'Clinical triage was explicitly assigned to the fictional complaint by development doctor 601. No case is saved yet. Switch the demo role to Patient and save the reviewed complaint.';
                                }
                            }
                        } else {
                            $result = $action === 'patient_create' ? $page['store']->createPatient($_POST, $draft) : $page['store']->create($_POST);
                            if (isset($result['case_id'])) {
                                $saved = $result['case_id'];
                                $_SESSION['mc_forms'][$nonce]['case_id'] = $saved;
                                if ($action === 'patient_create') unset($_SESSION['mc_patient_draft']);
                            }
                        }
                    }
                    if (isset($result['errors'])) {
                        if (http_response_code() !== 409) http_response_code(422);
                        $page['errors'] = $result['errors'];
                    } elseif ($saved !== null) {
                        $_SESSION['mc_saved'] = (int) $saved;
                        session_write_close();
                        header('Location: ' . chnPrototypeUrl('medical-cases.php#case-details', $uiRole, ['case' => $saved]), true, 303);
                        exit;
                    } elseif (isset($_SESSION['mc_intake_notice'])) {
                        session_write_close();
                        header('Location: ' . chnPrototypeUrl('medical-cases.php#' . ($uiRole === 'doctor' ? 'review-patient-case' : 'create-case'), $uiRole), true, 303);
                        exit;
                    }
                }
            }
        } else {
            if (isset($_SESSION['mc_saved'])) {
                $page['success'] = 'Case DEV-' . (int) $_SESSION['mc_saved'] . ' was saved to the local fictional database. Repeated submissions of the same form do not create another case.';
                unset($_SESSION['mc_saved']);
            }
            if (isset($_SESSION['mc_intake_notice'])) {
                $page['notice'] = $_SESSION['mc_intake_notice'];
                unset($_SESSION['mc_intake_notice']);
            }
        }
        $page['patient_draft'] = $_SESSION['mc_patient_draft'] ?? null;
        $review = $page['patient_draft']['review'] ?? null;
        $page['review_ready'] = $review && $review['expires'] >= time()
            && hash_equals($review['description_hash'], hash('sha256', $page['values']['case_description']))
            && (string) $review['department_id'] === $page['values']['department_id'];
        if (in_array($uiRole, ['patient', 'doctor'], true)) {
            $purpose = $uiRole === 'doctor' ? 'create_doctor' : ($page['review_ready'] ? 'patient_create' : 'patient_request');
            if ($page['submission'] === '' || ($_SESSION['mc_forms'][$page['submission']]['purpose'] ?? '') !== $purpose) $page['submission'] = mcDevFormToken($purpose);
        }
        if ($uiRole === 'doctor' && $page['patient_draft'] && ($page['review_submission'] === '' || ($_SESSION['mc_forms'][$page['review_submission']]['draft_id'] ?? '') !== $page['patient_draft']['id'])) $page['review_submission'] = mcDevFormToken('patient_review', $page['patient_draft']['id']);
        // Session lock serializes same-session POSTs through commit and nonce consumption.
    } catch (Throwable $error) {
        error_log('CHN case development database unavailable; code ' . $error->getCode());
        http_response_code(503);
        $page['store'] = null;
        $page['message'] = 'Local case storage is unavailable or a save could not be confirmed. Check the case list before retrying; ask the developer to check the local setup. No demonstration records are substituted.';
    }
    return $page;
}
