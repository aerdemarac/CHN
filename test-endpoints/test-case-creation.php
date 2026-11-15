<?php
/** CLI integration test. Requires the dedicated fictional DB and localhost:8776 dev server. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
if (getenv('CHN_CASE_DEV_MODE') !== '1') exit("Explicit CHN_CASE_DEV_MODE=1 required.\n");
require __DIR__ . '/../backend/medical-cases-dev.php';
$db = mcDevConnect();
$cookie = tempnam(sys_get_temp_dir(), 'chn-case-test-');
$created = [];
$diagnosisIds = [];
$checks = 0;
function verify(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function request(string $role = 'doctor', ?array $data = null, array $headers = [], string $path = '/medical-cases.php'): array {
    global $cookie;
    $curl = curl_init('http://127.0.0.1:8776' . $path . (str_contains($path, '?') ? '&' : '?') . 'role=' . rawurlencode($role));
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_TIMEOUT => 10, CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => $headers]);
    if ($data !== null) { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException('Local HTTP request failed.');
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);
    return ['status' => $status, 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
}
function form(): array {
    $response = request();
    verify($response['status'] === 200, 'Doctor page should load.');
    $values = [];
    foreach (['csrf_token', 'submission_token'] as $field) {
        verify(preg_match('/name="' . $field . '" value="([a-f0-9]{64})"/', $response['body'], $match) === 1, 'Security token missing.');
        $values[$field] = $match[1];
    }
    return $values;
}
function caseCount(): int { global $db; return (int) $db->query('SELECT COUNT(*) FROM `case`')->fetchColumn(); }
try {
    $initial = caseCount();
    $tokens = form();
    $valid = ['patient_id' => '501', 'department_id' => '1', 'doctor_id' => '601', 'tier_color' => 'green', 'case_description' => 'CHN LOCAL TEST: persisted case creation, fictional data only.'];
    foreach (['patient', 'staff', 'admin', 'invalid'] as $role) {
        $response = request($role, $tokens + $valid);
        verify($response['status'] === 403, "$role creation must be rejected.");
        verify(caseCount() === $initial, 'Unauthorized POST inserted a case.');
    }
    foreach ([['csrf_token' => 'invalid'], ['csrf_token' => ['bad']], ['csrf_token' => '']] as $override) {
        verify(request('doctor', array_replace($tokens + $valid, $override))['status'] === 403, 'Invalid CSRF accepted.');
    }
    verify(request('doctor', array_replace($tokens + $valid, ['submission_token' => str_repeat('a', 64)]))['status'] === 409, 'Invalid submission token accepted.');
    verify(request('doctor', $tokens + $valid, ['Origin: https://untrusted.example'])['status'] === 403, 'Cross-origin POST accepted.');
    verify(request('doctor', $tokens + $valid, ['Host: untrusted.example'])['status'] === 403, 'Untrusted Host accepted.');
    foreach ([['patient_id' => ''], ['patient_id' => '999999'], ['patient_id' => '0'], ['patient_id' => '-1'], ['patient_id' => '1.5'], ['patient_id' => '2147483648'], ['patient_id' => ['501']], ['department_id' => ''], ['department_id' => '999999'], ['department_id' => '2', 'doctor_id' => '602'], ['doctor_id' => '602'], ['doctor_id' => '999999'], ['doctor_id' => ['601']], ['tier_color' => ''], ['tier_color' => 'red'], ['tier_color' => ['green']], ['case_description' => str_repeat('x', 65536)], ['case_description' => ['bad']]] as $override) {
        $response = request('doctor', array_replace($tokens + $valid, $override));
        verify($response['status'] === 422, 'Invalid fields accepted: ' . implode(',', array_keys($override)));
        verify(str_contains($response['body'], 'Review the following fields'), 'Field feedback missing.');
        verify(caseCount() === $initial, 'Invalid values persisted.');
    }
    foreach ([['green', '501', '601', $valid['case_description']], ['yellow', '502', '601', 'CHN LOCAL TEST: second fictional patient.'], ['orange', '501', '', '']] as $index => $values) {
        $tokens = form();
        [$color, $patient, $doctor, $description] = $values;
        $payload = $tokens + ['patient_id' => $patient, 'department_id' => '1', 'doctor_id' => $doctor, 'tier_color' => $color, 'case_description' => $description,
            'case_state' => 'closed', 'case_id' => '999999', 'created_at' => '2000-01-01 00:00:00', 'user_id' => '999999', 'role' => 'admin'];
        $response = request('doctor', $payload);
        verify($response['status'] === 303, 'Valid insert did not redirect.');
        verify(preg_match('/Location: (.+)/i', $response['headers'], $match) === 1, 'Missing redirect.');
        $location = trim($match[1]);
        parse_str(parse_url($location, PHP_URL_QUERY), $params);
        verify(($params['role'] ?? '') === 'doctor', 'Redirect lost role.');
        $id = (int) $params['case'];
        $created[] = $id;
        $statement = $db->prepare('SELECT * FROM `case` WHERE case_id=?');
        $statement->execute([$id]); $record = $statement->fetch();
        verify($record && $record['case_state'] === 'open' && $record['tier_color'] === $color && $record['created_at'] !== '2000-01-01 00:00:00' && $record['created_at'] !== null, 'Stored state/timestamp/triage incorrect.');
        verify((int) $record['patient_id'] === (int) $patient && ($doctor === '' ? $record['doctor_id'] === null : (int) $record['doctor_id'] === 601), 'Stored assignments incorrect.');
        if ($description === '') verify($record['case_description'] === null, 'Optional description should be NULL.');
        $detail = request('doctor', null, [], '/medical-cases.php?case=' . $id);
        verify(str_contains($detail['body'], 'Case DEV-' . $id . '</h2>') && str_contains($detail['body'], 'was saved to the local fictional database'), 'Saved detail/real success not visible.');
        $refresh = request('doctor', null, [], '/medical-cases.php?case=' . $id);
        verify(str_contains($refresh['body'], 'Case DEV-' . $id . '</h2>') && !str_contains($refresh['body'], 'was saved to the local fictional database'), 'Refresh did not retain case or clear one-time flash.');
        verify(request('doctor', $payload)['status'] === 303 && caseCount() === $initial + $index + 1, 'Duplicate POST inserted again.');
        $patientView = request('patient', null, [], '/medical-cases.php?case=' . $id);
        verify(str_contains($patientView['body'], 'Case DEV-' . $id . '</h2>') === ($patient === '501'), 'Patient scope incorrect.');
    }
    verify(caseCount() === $initial + 3, 'Expected exactly three inserted cases.');
    // Exercise the canonical composite FK independently of the application's validation.
    try {
        $db->beginTransaction();
        $db->exec("INSERT INTO `case` (patient_id,department_id,doctor_id,tier_color) VALUES (501,1,602,'green')");
        $db->rollBack();
        verify(false, 'Composite doctor/department FK was not enforced.');
    } catch (PDOException $error) {
        if ($db->inTransaction()) $db->rollBack();
        verify($error->getCode() === '23000', 'Expected FK violation.');
    }
    // Temporary, explicitly synthetic relational rendering tests; removed in finally.
    $statement = $db->prepare('INSERT INTO diagnosis (case_id,doctor_id,diagnosis_text,icd_code,diagnosed_at) VALUES (?,?,?,?,?)');
    foreach ([['SYNTHETIC TEST ONLY: older relation', null, '2026-11-14 10:00:00'], ['SYNTHETIC TEST ONLY: newer relation', 'TEST-ONLY', '2026-11-15 10:00:00']] as $diagnosis) {
        $statement->execute([$created[0], null, $diagnosis[0], $diagnosis[1], $diagnosis[2]]);
        $diagnosisIds[] = (int) $db->lastInsertId();
    }
    $detail = request('doctor', null, [], '/medical-cases.php?case=' . $created[0])['body'];
    verify(str_contains($detail, 'SYNTHETIC TEST ONLY: older relation') && str_contains($detail, 'SYNTHETIC TEST ONLY: newer relation'), 'Multiple diagnoses did not render.');
    verify(strpos($detail, 'newer relation') < strpos($detail, 'older relation') && str_contains($detail, 'ICD code: TEST-ONLY'), 'Diagnosis order/ICD incorrect.');
    $other = request('doctor', null, [], '/medical-cases.php?case=' . $created[1])['body'];
    verify(!str_contains($other, 'SYNTHETIC TEST ONLY:') && str_contains($other, 'No diagnoses available'), 'Diagnoses crossed cases or missing empty state.');
    $staff = request('staff', null, [], '/medical-cases.php?case=' . $created[0])['body'];
    verify(!str_contains($staff, 'SYNTHETIC TEST ONLY:') && !str_contains($staff, 'create-case-form') && !str_contains($staff, $valid['case_description']), 'Staff received clinical data/actions.');
    foreach (['staff', 'admin'] as $role) verify(!str_contains(request($role)['body'], 'create-case-form'), "$role has a creation form.");
    $patientForm = request('patient')['body'];
    verify(str_contains($patientForm, 'create-case-form') && !str_contains($patientForm, 'name="tier_color"'), 'Patient intake form must exist without triage controls.');
    verify(request('doctor', null, [], '/backend/medical-cases-dev.php')['status'] === 403, 'Library exposed directly.');
    verify(request('doctor', null, [], '/test-endpoints/setup-case-dev.php')['status'] === 403, 'CLI setup exposed over HTTP.');
    echo "$checks checks passed. Persisted fictional cases: " . implode(', ', $created) . ".\n";
} finally {
    if ($diagnosisIds) {
        $statement = $db->prepare('DELETE FROM diagnosis WHERE diagnosis_id=?');
        foreach ($diagnosisIds as $id) $statement->execute([$id]);
        echo "Removed synthetic diagnosis test rows: " . implode(', ', $diagnosisIds) . ".\n";
    }
    if (is_file($cookie)) unlink($cookie);
}
