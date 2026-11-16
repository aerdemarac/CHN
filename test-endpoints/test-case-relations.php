<?php
/** Explicit isolated database regression; temporary lookup/diagnosis data restored in finally. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
if (getenv('CHN_CASE_DEV_MODE') !== '1') exit("Set CHN_CASE_DEV_MODE=1.\n");
require dirname(__DIR__) . '/backend/medical-cases-dev.php';
$db = mcDevConnect();
$ids = [];
try {
    $case = $db->query('SELECT case_id FROM `case` WHERE patient_id=501 ORDER BY case_id LIMIT 1')->fetchColumn();
    if (!$case) throw new RuntimeException('Run the HTTP creation suite first.');
    foreach (['2026-01-01 10:00:00','2026-02-01 10:00:00'] as $date) {
        $statement = $db->prepare('INSERT INTO diagnosis (case_id,doctor_id,diagnosis_text,diagnosed_at) VALUES (?,601,?,?)');
        $statement->execute([$case, 'SYNTHETIC RELATION CHECK ' . $date, $date]);
        $ids[] = $db->lastInsertId();
    }
    $store = new McCaseDevStore($db);
    $records = $store->records('patient');
    $diagnoses = array_values(array_filter($records['diagnoses'], fn($row) => (string)$row['case_id'] === (string)$case));
    if (count($diagnoses) !== 2 || $diagnoses[0]['diagnosed_at'] !== '2026-02-01 10:00:00') throw new RuntimeException('Diagnosis relation/order failed.');
    if ($store->records('staff')['diagnoses'] !== []) throw new RuntimeException('Staff diagnosis disclosure.');
    $db->exec("UPDATE `user` SET national_id='DEMO-901' WHERE user_id=902");
    if (!isset($store->createDoctor(['national_id'=>'DEMO-901'])['errors']['national_id'])) throw new RuntimeException('Ambiguous ID accepted.');
    echo "Diagnosis relation/order, staff isolation and ambiguous National ID checks passed.\n";
} finally {
    foreach ($ids as $id) { $statement = $db->prepare('DELETE FROM diagnosis WHERE diagnosis_id=?'); $statement->execute([$id]); }
    $db->exec("UPDATE `user` SET national_id='DEMO-902' WHERE user_id=902");
}
