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

    public function assignedDoctor(): array { return $this->actor(); }

    public function options(): array
    {
        $actor = $this->actor();
        $patients = $this->db->query("SELECT p.patient_id, CONCAT(u.name, ' ', u.surname) patient_name FROM patient p JOIN `user` u ON u.user_id=p.user_id WHERE u.role='patient' AND ((p.patient_id=501 AND u.email='case-dev-patient-a@example.invalid') OR (p.patient_id=502 AND u.email='case-dev-patient-b@example.invalid')) ORDER BY p.patient_id")->fetchAll();
        $statement = $this->db->prepare('SELECT department_id, department_name FROM department WHERE hospital_id=(SELECT hospital_id FROM department WHERE department_id=?)');
        $statement->execute([$actor['department_id']]);
        $departments = $statement->fetchAll();
        $statement = $this->db->prepare("SELECT d.doctor_id, d.department_id, CONCAT(u.name, ' ', u.surname) doctor_name FROM doctor d JOIN `user` u ON u.user_id=d.user_id JOIN department dp ON dp.department_id=d.department_id WHERE dp.hospital_id=(SELECT hospital_id FROM department WHERE department_id=?) AND u.role='doctor'");
        $statement->execute([$actor['department_id']]);
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

    public function createPatient(array $input, ?array $unused = null): array
    {
        if (isset($input['patient_id']) || isset($input['user_id']) || isset($input['tier_color'])) return ['errors' => ['case_title' => 'Patient identity and triage are supplied by the server.']];
        $input['patient_id'] = (string) $this->patientIdentity()['patient_id'];
        return $this->create($input);
    }

    public function createDoctor(array $input): array
    {
        $national = $input['national_id'] ?? '';
        if (!is_string($national) || !preg_match('/\A[A-Za-z0-9-]{3,50}\z/D', $national)) return ['errors' => ['national_id' => 'Enter a valid Patient National ID (3–50 letters, digits or hyphens).']];
        $statement = $this->db->prepare("SELECT p.patient_id FROM `user` u JOIN patient p ON p.user_id=u.user_id WHERE u.national_id=? AND u.role='patient' LIMIT 2");
        $statement->execute([$national]);
        $matches = $statement->fetchAll();
        if (count($matches) !== 1) return ['errors' => ['national_id' => 'Patient could not be uniquely resolved. Ask the care team to verify the record.']];
        $actor = $this->actor();
        $input['doctor_id'] = (string) $actor['doctor_id'];
        $input['department_id'] = (string) $actor['department_id'];
        $input['patient_id'] = (string) $matches[0]['patient_id'];
        return $this->create($input);
    }

    public function updatePatientStatus(array $input): array
    {
        if (!mcDevAllowed()) throw new RuntimeException('Local capability required.');
        $patient = $this->patientIdentity();
        $id = $input['case_id'] ?? '';
        $state = $input['case_state'] ?? '';
        if (!is_string($id) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', $id)
            || !is_string($state) || !in_array($state, ['open', 'closed'], true)) {
            return ['errors' => ['case_state' => 'Select a valid case and status.']];
        }
        $statement = $this->db->prepare("UPDATE `case` SET case_state=?, closed_by_patient_id=CASE WHEN ?='closed' THEN patient_id ELSE NULL END, closed_by_doctor_id=NULL WHERE case_id=? AND patient_id=? AND case_state<>?");
        try {
            $statement->execute([$state, $state, $id, $patient['patient_id'], $state]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '45000') throw $error;
            return ['errors' => ['case_state' => 'This case cannot be closed while active treatments exist. Contact your care team.']];
        }
        $statement = $this->db->prepare('SELECT case_id FROM `case` WHERE case_id=? AND patient_id=?');
        $statement->execute([$id, $patient['patient_id']]);
        return $statement->fetchColumn() ? ['case_id' => (int) $id]
            : ['errors' => ['case_state' => 'Case unavailable for this patient.']];
    }

    public function updateDoctorStatus(array $input): array
    {
        if (!mcDevAllowed() || (getenv('CHN_CASE_DEV_ACTOR') ?: 'doctor') !== 'doctor') {
            return ['errors' => ['case_state' => 'Doctor capability required.']];
        }
        $actor = $this->actor();
        $id = $input['case_id'] ?? '';
        $state = $input['case_state'] ?? '';
        if (!is_string($id) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', $id)
            || !is_string($state) || !in_array($state, ['open', 'closed'], true)) {
            return ['errors' => ['case_state' => 'Select a valid case and status.']];
        }
        $scope = 'case_id=? AND department_id=? AND (doctor_id=? OR doctor_id IS NULL)';
        $parameters = [$id, $actor['department_id'], $actor['doctor_id']];
        $statement = $this->db->prepare("UPDATE `case` SET case_state=?, closed_by_doctor_id=CASE WHEN ?='closed' THEN ? ELSE NULL END, closed_by_patient_id=NULL WHERE $scope AND case_state<>?");
        try {
            $statement->execute(array_merge([$state, $state, $actor['doctor_id']], $parameters, [$state]));
        } catch (PDOException $error) {
            if ($error->getCode() !== '45000') throw $error;
            return ['errors' => ['case_state' => 'This case cannot be closed while active treatments exist.']];
        }
        $statement = $this->db->prepare("SELECT case_id FROM `case` WHERE $scope");
        $statement->execute($parameters);
        return $statement->fetchColumn() ? ['case_id' => (int) $id]
            : ['errors' => ['case_state' => 'Case unavailable for this doctor.']];
    }

    public function updateTriage(array $input): array
    {
        if (!mcDevAllowed()) throw new RuntimeException('Local capability required.');
        // Explicit server configuration grants one fictional actor; URL role never grants it.
        $capability = getenv('CHN_CASE_DEV_ACTOR') ?: 'doctor';
        $department = (int) $this->actor()['department_id'];
        if ($capability === 'staff') {
            $statement = $this->db->prepare("SELECT s.department_id FROM staff s JOIN `user` u ON u.user_id=s.user_id WHERE s.staff_id=701 AND u.role='healthcare_staff' AND u.email='case-dev-staff@example.invalid' AND s.hospital_id=1");
            $statement->execute();
            $department = (int) $statement->fetchColumn();
        } elseif ($capability !== 'doctor') return ['errors' => ['triage' => 'No clinical update capability.']];
        $id = $input['case_id'] ?? '';
        $color = $input['tier_color'] ?? '';
        if (!is_string($id) || !ctype_digit($id) || !is_string($color) || !in_array($color, ['green','yellow','orange'], true)) return ['errors' => ['triage' => 'Select a valid case and triage.']];
        $scope = $capability === 'doctor' ? ' AND (doctor_id=601 OR doctor_id IS NULL)' : '';
        $statement = $this->db->prepare("UPDATE `case` SET tier_color=? WHERE case_id=? AND department_id=?" . $scope);
        $statement->execute([$color, $id, $department]);
        $statement = $this->db->prepare("SELECT case_id FROM `case` WHERE case_id=? AND department_id=?" . $scope);
        $statement->execute([$id, $department]);
        return $statement->fetchColumn() ? ['case_id' => (int) $id] : ['errors' => ['triage' => 'Case unavailable for this actor.']];
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
        $title = $role === 'staff' ? 'NULL' : 'c.case_title';
        $statement = $this->db->prepare("SELECT c.case_id,c.patient_id,c.department_id,c.doctor_id,c.case_state,c.tier_color,c.created_at, $description case_description, $title case_title, dp.department_name, CONCAT(pu.name,' ',pu.surname) patient_name, CONCAT(du.name,' ',du.surname) doctor_name FROM `case` c JOIN department dp ON dp.department_id=c.department_id JOIN patient p ON p.patient_id=c.patient_id JOIN `user` pu ON pu.user_id=p.user_id LEFT JOIN doctor d ON d.doctor_id=c.doctor_id LEFT JOIN `user` du ON du.user_id=d.user_id WHERE {$scope[0]} ORDER BY c.created_at DESC,c.case_id DESC");
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

    /** Internal shared insert; callers supply a server-resolved fictional patient. */
    private function create(array $input): array
    {
        if (!mcDevAllowed()) throw new RuntimeException('Local write capability required.');
        $errors = [];
        $ids = [];
        foreach (['patient_id', 'department_id', 'doctor_id'] as $field) {
            $raw = $input[$field] ?? '';
            $id = is_string($raw) && preg_match('/\A[1-9][0-9]{0,9}\z/D', $raw)
                ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) : false;
            if ($id === false) $errors[$field] = 'Select a valid ' . str_replace('_id', '', $field) . '.';
            $ids[$field] = $id;
        }
        $title = $input['case_title'] ?? '';
        if (!is_string($title) || !mb_check_encoding($title, 'UTF-8') || trim($title) === '' || mb_strlen($title, 'UTF-8') > 160) $errors['case_title'] = 'Enter a short Case Title (1–160 characters).';
        $description = $input['case_description'] ?? '';
        if (!is_string($description) || !mb_check_encoding($description, 'UTF-8') || trim($description) === '' || strlen($description) > 65535) $errors['case_description'] = 'Enter a detailed description (up to 65,535 UTF-8 bytes).';
        if ($errors) return ['errors' => $errors];

        $this->db->beginTransaction();
        try {
            $actor = $this->actor();
            $options = $this->options();
            if (!in_array($ids['patient_id'], array_column($options['patients'], 'patient_id'))) $errors['patient_id'] = 'Select an available fictional patient.';
            if (!in_array($ids['department_id'], array_map('intval', array_column($options['departments'], 'department_id')), true)) $errors['department_id'] = 'Select an available development department.';
            if ($ids['doctor_id'] !== null) {
                $statement = $this->db->prepare('SELECT department_id FROM doctor WHERE doctor_id=? FOR UPDATE');
                $statement->execute([$ids['doctor_id']]);
                $department = $statement->fetchColumn();
                if ($department === false || (int) $department !== $ids['department_id']) $errors['doctor_id'] = 'The responsible doctor must belong to the selected department.';

            }
            if ($errors) { $this->db->rollBack(); return ['errors' => $errors]; }
            // IDs/timestamp generated by MySQL; case_state is never accepted from the browser.
            $statement = $this->db->prepare("INSERT INTO `case` (patient_id,department_id,doctor_id,case_title,case_description,tier_color,case_state) VALUES (?,?,?,?,?,'green','open')");
            $statement->execute([$ids['patient_id'], $ids['department_id'], $ids['doctor_id'], trim($title), $description]);
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
function mcDevFormToken(string $purpose): string
{
    while (count($_SESSION['mc_forms']) >= 20) array_shift($_SESSION['mc_forms']);
    $token = bin2hex(random_bytes(32));
    $_SESSION['mc_forms'][$token] = ['time' => time(), 'case_id' => null, 'purpose' => $purpose];
    return $token;
}

/** Request orchestration for the shared page. Sessions hold security tokens, never active roles. */
function mcDevPage(string $uiRole): array
{
    $page = ['enabled' => mcDevAllowed(), 'store' => null, 'options' => ['patients' => [], 'departments' => [], 'doctors' => []],
        'errors' => [], 'message' => '', 'notice' => '', 'success' => '', 'csrf' => '', 'submission' => '',

        'values' => ['patient_id' => '', 'department_id' => '', 'doctor_id' => '', 'case_title' => '', 'national_id' => '', 'case_description' => '']];
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
        if ($uiRole === 'doctor') {
            $page['options']['departments'] = array_values(array_filter($page['options']['departments'], fn(array $department): bool => (int) $department['department_id'] === (int) $page['store']->assignedDoctor()['department_id']));
        }
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
        $page['csrf'] = $_SESSION['mc_csrf'];
        if ($post) {
            // URL role restricts the UI action further, but only the local server gate grants test capability.
            $action = $_POST['action'] ?? ($uiRole === 'doctor' ? 'create_doctor' : 'patient_create');
            $allowed = ['patient' => ['patient_create', 'update_status'], 'doctor' => ['create_doctor', 'update_triage', 'update_doctor_status'], 'staff' => ['update_triage'], 'admin' => []][$uiRole];
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $csrf = $_POST['csrf_token'] ?? '';
            $forbiddenPatientFields = $uiRole === 'patient' && (array_key_exists('patient_id', $_POST) || array_key_exists('user_id', $_POST)  || array_key_exists('tier_color', $_POST));
            if (!is_string($action) || !in_array($action, $allowed, true) || $forbiddenPatientFields
                || (($action === 'update_triage' || $action === 'create_doctor' || $action === 'update_doctor_status') && $uiRole !== (getenv('CHN_CASE_DEV_ACTOR') ?: 'doctor'))
                || ($origin !== '' && $origin !== 'http://' . $_SERVER['HTTP_HOST'])
                || !is_string($csrf) || !hash_equals($page['csrf'], $csrf)) {
                http_response_code(403);
                $page['message'] = 'Creation was rejected. Use the correct local form and security token. Patient identity and clinical triage cannot be supplied by a patient.';
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
                    if (in_array($action, ['patient_create', 'create_doctor'], true)) $page['submission'] = $nonce;
                    $saved = $_SESSION['mc_forms'][$nonce]['case_id'] ?? null;
                    $result = [];
                    if ($saved === null) {
                        $result = match ($action) {
                            'patient_create' => $page['store']->createPatient($_POST),
                            'create_doctor' => $page['store']->createDoctor($_POST),
                            'update_doctor_status' => $page['store']->updateDoctorStatus($_POST),
                            'update_status' => $page['store']->updatePatientStatus($_POST),
                            'update_triage' => $page['store']->updateTriage($_POST),
                        };
                        if (isset($result['case_id'])) {
                            $saved = $result['case_id'];
                            $_SESSION['mc_forms'][$nonce]['case_id'] = $saved;
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
                    }
                }
            }
        } else {
            if (isset($_SESSION['mc_saved'])) {
                $page['success'] = 'Case DEV-' . (int) $_SESSION['mc_saved'] . ' was saved to the local fictional database. Repeated submissions of the same form do not create another case.';
                unset($_SESSION['mc_saved']);
            }
        }
        if (in_array($uiRole, ['patient', 'doctor'], true)) {
            $purpose = $uiRole === 'doctor' ? 'create_doctor' : 'patient_create';
            if ($page['submission'] === '' || ($_SESSION['mc_forms'][$page['submission']]['purpose'] ?? '') !== $purpose) $page['submission'] = mcDevFormToken($purpose);
        }
        $page['status_submission'] = mcDevFormToken($uiRole === 'doctor' ? 'update_doctor_status' : 'update_status');
        $page['triage_submission'] = mcDevFormToken('update_triage');
        // Session lock serializes same-session POSTs through commit and nonce consumption.
    } catch (Throwable $error) {
        error_log('CHN case development database unavailable; code ' . $error->getCode());
        http_response_code(503);
        $page['store'] = null;
        $page['message'] = 'Local case storage is unavailable or a save could not be confirmed. Check the case list before retrying; ask the developer to check the local setup. No demonstration records are substituted.';
    }
    return $page;
}
