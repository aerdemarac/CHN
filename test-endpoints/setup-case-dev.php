<?php
/** CLI-only setup of a NEW isolated database using the unmodified canonical schema. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
if (getenv('CHN_CASE_DEV_MODE') !== '1') exit("Set CHN_CASE_DEV_MODE=1 to explicitly enable fictional development setup.\n");
require __DIR__ . '/../backend/medical-cases-dev.php';
try {
    $db = mcDevConnect(true);
    // No IF NOT EXISTS: refuse to overwrite or reseed any existing database.
    $db->exec('CREATE DATABASE `chn_case_creation_dev` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $schema = file_get_contents(__DIR__ . '/../db/schema.sql');
    $schema = str_replace('`chn_db`', '`chn_case_creation_dev`', $schema);
    [$tables, $triggers] = explode('DELIMITER $$', $schema, 2);
    $db->exec($tables);
    $triggers = str_replace('DELIMITER ;', '', $triggers);
    foreach (explode('$$', $triggers) as $trigger) {
        if (trim($trigger) !== '') $db->exec(trim($trigger));
    }
    $db->exec(file_get_contents(dirname(__DIR__) . '/db/migrations/20261116_case_title.sql'));
    $migration = file_get_contents(dirname(__DIR__) . '/db/migrations/20261116_patient_case_status.sql');
    [$ddl, $trigger] = explode('DELIMITER $$', $migration, 2);
    $db->exec($ddl);
    $db->exec(trim(str_replace(['$$', 'DELIMITER ;'], '', $trigger)));
    $db->beginTransaction();
    $statement = $db->prepare('INSERT INTO `user` (user_id,email,password,role,name,surname) VALUES (?,?,?,?,?,?)');
    foreach ([
        [901, 'case-dev-patient-a@example.invalid', 'patient', 'Demo Patient', 'A'],
        [902, 'case-dev-patient-b@example.invalid', 'patient', 'Demo Patient', 'B'],
        [903, 'case-dev-doctor@example.invalid', 'doctor', 'Demo Doctor', 'A'],
        [904, 'case-dev-other-doctor@example.invalid', 'doctor', 'Demo Doctor', 'B'],
        [905, 'case-dev-staff@example.invalid', 'healthcare_staff', 'Demo Staff', 'A'],
    ] as $user) {
        $statement->execute([$user[0], $user[1], password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $user[2], $user[3], $user[4]]);
    }
    $db->exec("UPDATE `user` SET national_id=CONCAT('DEMO-',user_id) WHERE user_id IN (901,902)");
    $db->exec("INSERT INTO hospital (hospital_id,hospital_name,hospital_type) VALUES (1,'FICTIONAL LOCAL TEST HOSPITAL','public')");
    $db->exec("INSERT INTO department (department_id,department_name,hospital_id) VALUES (1,'Demo Internal Medicine',1),(2,'Demo Orthopedics',1)");
    $db->exec('INSERT INTO patient (patient_id,user_id) VALUES (501,901),(502,902)');
    $db->exec('INSERT INTO doctor (doctor_id,user_id,department_id) VALUES (601,903,1),(602,904,2)');
    $db->exec('INSERT INTO staff (staff_id,user_id,hospital_id,department_id) VALUES (701,905,1,1)');
    $db->commit();
    echo "Created chn_case_creation_dev using canonical tables and triggers.\nSeeded 5 fictional users, 2 patients (501/502), 2 doctors (601/602), 2 departments (1/2), 1 explicitly fictional test hospital. No cases or diagnoses seeded.\n";
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, "Setup refused or failed (code " . $error->getCode() . "). Existing databases are not overwritten. Check local MySQL access and whether the dev database already exists.\n");
    exit(1);
}
