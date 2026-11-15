<?php
/** CLI-only regression of clinician-reviewed patient creation in the isolated local DB. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
if (getenv('CHN_CASE_DEV_MODE') !== '1') exit("Explicit CHN_CASE_DEV_MODE=1 required.\n");
require __DIR__ . '/../backend/medical-cases-dev.php';
$db = mcDevConnect();
$cookie = tempnam(sys_get_temp_dir(), 'chn-patient-case-test-');
$checks = 0;
function patientCheck(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function patientHttp(string $role, ?array $data = null, string $query = ''): array {
    global $cookie;
    $curl = curl_init('http://127.0.0.1:8776/medical-cases.php?role=' . rawurlencode($role) . $query);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_TIMEOUT => 10, CURLOPT_PROXY => '']);
    if ($data !== null) { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException('HTTP test server unavailable.');
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);
    return ['status' => $status, 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
}
function patientForm(string $role, string $id = 'create-case-form'): array {
    $response = patientHttp($role);
    patientCheck($response['status'] === 200, 'Form GET failed.');
    patientCheck(preg_match('/<form[^>]*id="' . $id . '"[^>]*>(.*?)<\/form>/s', $response['body'], $match) === 1, 'Expected form missing.');
    $fields = [];
    preg_match_all('/<input[^>]*name="([^"]+)" value="([^"]*)"/', $match[1], $inputs, PREG_SET_ORDER);
    foreach ($inputs as $input) $fields[$input[1]] = html_entity_decode($input[2], ENT_QUOTES, 'UTF-8');
    return $fields;
}
function patientCount(): int { global $db; return (int) $db->query('SELECT COUNT(*) FROM `case`')->fetchColumn(); }
try {
    $initial = patientCount();
    $form = patientForm('patient');
    $complaint = ['department_id' => '1', 'case_description' => 'CHN PATIENT LOCAL TEST: fictional complaint requiring explicit clinician review.'];
    foreach ([['case_description' => ''], ['case_description' => '   '], ['case_description' => ['bad']], ['case_description' => str_repeat('x', 65536)], ['department_id' => ''], ['department_id' => '999999'], ['department_id' => ['1']], ['department_id' => '2']] as $override) {
        patientCheck(patientHttp('patient', array_replace($form + $complaint, $override))['status'] === 422, 'Invalid patient draft accepted.');
        patientCheck(patientCount() === $initial, 'Invalid draft inserted a case.');
    }
    foreach (['patient_id' => '502', 'user_id' => '902', 'doctor_id' => '601', 'tier_color' => 'green'] as $key => $value) {
        patientCheck(patientHttp('patient', $form + $complaint + [$key => $value])['status'] === 403, 'Patient-controlled identity/clinical field accepted.');
    }
    patientCheck(patientHttp('patient', array_replace($form + $complaint, ['action' => 'patient_create']))['status'] === 403, 'Intake token used to bypass review.');
    patientCheck(patientHttp('doctor', array_replace($form + $complaint, ['action' => 'create_doctor']))['status'] === 403, 'Patient token used for doctor creation.');
    patientCheck(patientHttp('patient', array_replace($form + $complaint, ['csrf_token' => 'invalid']))['status'] === 403, 'Invalid patient CSRF accepted.');
    patientCheck(patientHttp('patient', $form + $complaint)['status'] === 303, 'Valid complaint did not enter clinical review.');
    patientCheck(patientCount() === $initial, 'Unreviewed complaint inserted as a case.');
    $waiting = patientHttp('patient')['body'];
    patientCheck(str_contains($waiting, 'No case has been saved to MySQL') && !str_contains($waiting, 'was saved to the local fictional database'), 'Draft falsely reported as persisted case.');
    $staleReview = patientForm('doctor', 'review-patient-form');
    $new = patientForm('patient');
    $complaint['case_description'] .= ' Updated before review.';
    patientCheck(patientHttp('patient', $new + $complaint)['status'] === 303, 'Replacement draft failed.');
    patientCheck(patientHttp('doctor', $staleReview + ['tier_color' => 'yellow'])['status'] === 409, 'Stale review approved a changed complaint.');
    $review = patientForm('doctor', 'review-patient-form');
    foreach (['', 'red', ['green']] as $triage) patientCheck(patientHttp('doctor', $review + ['tier_color' => $triage])['status'] === 422, 'Invalid clinician classification accepted.');
    patientCheck(patientHttp('patient', $review + ['tier_color' => 'yellow'])['status'] === 403, 'Patient approved clinical triage.');
    patientCheck(patientHttp('doctor', $review + ['tier_color' => 'yellow'])['status'] === 303, 'Explicit clinician review failed.');
    patientCheck(patientCount() === $initial, 'Review prematurely inserted a case.');
    $save = patientForm('patient');
    patientCheck($save['action'] === 'patient_create', 'Patient save unavailable after review.');
    patientCheck(patientHttp('patient', $save + $complaint + ['tier_color' => 'orange'])['status'] === 403, 'Patient overrode clinician triage.');
    $changed = $complaint; $changed['case_description'] .= ' Changed after review.';
    $rejected = patientHttp('patient', $save + $changed);
    patientCheck($rejected['status'] === 422 && str_contains($rejected['body'], 'review this exact complaint'), 'Changed complaint reused clinical approval.');
    patientCheck(patientCount() === $initial, 'Changed/unreviewed complaint persisted.');
    // Return to the exact clinician-reviewed complaint; identity/state remain server-controlled.
    $save = patientForm('patient');
    $payload = $save + $complaint + ['case_state' => 'closed', 'case_id' => '999999', 'created_at' => '2000-01-01 00:00:00'];
    $response = patientHttp('patient', $payload);
    patientCheck($response['status'] === 303 && preg_match('/Location: (.+)/i', $response['headers'], $match) === 1, 'Patient case did not save.');
    parse_str(parse_url(trim($match[1]), PHP_URL_QUERY), $params);
    $id = (int) ($params['case'] ?? 0);
    patientCheck($id > 0 && $params['role'] === 'patient', 'Patient redirect lost saved ID/role.');
    $statement = $db->prepare('SELECT * FROM `case` WHERE case_id=?'); $statement->execute([$id]); $row = $statement->fetch();
    patientCheck((int) $row['patient_id'] === 501 && (int) $row['doctor_id'] === 601 && (int) $row['department_id'] === 1 && $row['tier_color'] === 'yellow' && $row['case_state'] === 'open', 'Persisted patient/clinical values incorrect.');
    patientCheck($row['case_description'] === $complaint['case_description'] && $row['created_at'] !== null && $row['created_at'] !== '2000-01-01 00:00:00', 'Complaint/default timestamp incorrect.');
    $detail = patientHttp('patient', null, '&case=' . $id)['body'];
    patientCheck(str_contains($detail, 'Case DEV-' . $id . '</h2>') && str_contains($detail, 'was saved to the local fictional database'), 'Saved patient detail/confirmation missing.');
    $refresh = patientHttp('patient', null, '&case=' . $id)['body'];
    patientCheck(str_contains($refresh, $complaint['case_description']) && str_contains($refresh, 'Case DEV-' . $id . '</h2>') && str_contains($refresh, 'No diagnoses available'), 'Patient detail did not survive refresh.');
    patientCheck(patientHttp('patient', $payload)['status'] === 303 && patientCount() === $initial + 1, 'Duplicate patient POST inserted twice.');
    patientCheck(!str_contains(patientHttp('doctor')['body'], 'id="review-patient-form"'), 'Saved draft remained in review queue.');
    patientCheck(!str_contains($refresh, 'name="tier_color"') && !str_contains($refresh, 'name="patient_id"'), 'Patient received clinical/identity inputs.');
    foreach (['staff', 'admin'] as $role) patientCheck(patientHttp($role, $payload)['status'] === 403, 'Unapproved role created patient case.');
    echo "$checks patient creation checks passed. Persisted fictional patient case: $id (patient 501, clinician 601, clinician-selected yellow).\n";
} finally {
    if (is_file($cookie)) unlink($cookie);
}
