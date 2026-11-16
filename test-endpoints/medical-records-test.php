<?php
$recordsRoles = [
    'patient'          => 'Patient',
    'doctor'           => 'Doctor',
    'healthcare_staff' => 'Healthcare Staff',
    'admin'            => 'Admin',
];

$recordsRole = isset($_GET['role']) && is_string($_GET['role']) && isset($recordsRoles[$_GET['role']])
    ? $_GET['role']
    : 'patient';

$recordsToday = new DateTimeImmutable('today');
$recordsTodayKey = $recordsToday->format('Y-m-d');

$recordsTiers = [
    'orange' => ['label' => 'Orange', 'note' => 'Needs care',    'rank' => 0],
    'yellow' => ['label' => 'Yellow', 'note' => 'Important',     'rank' => 1],
    'green'  => ['label' => 'Green',  'note' => 'No vital risk', 'rank' => 2],
];

$recordsAppointmentStatuses = [
    'pending'   => ['label' => 'Pending',   'badge' => 'warning'],
    'confirmed' => ['label' => 'Confirmed', 'badge' => 'success'],
    'cancelled' => ['label' => 'Cancelled', 'badge' => 'danger'],
    'completed' => ['label' => 'Completed', 'badge' => 'neutral'],
];

$recordsTreatmentStatuses = [
    'active'   => ['label' => 'Active',   'badge' => 'info'],
    'inactive' => ['label' => 'Inactive', 'badge' => 'neutral'],
];

$recordsStates = [
    'open'   => ['label' => 'Open',   'badge' => 'success'],
    'closed' => ['label' => 'Closed', 'badge' => 'neutral'],
];

function recordsEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function recordsDate(int $days): string
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

function recordsQuery(array $params): string
{
    return '?' . http_build_query($params);
}

function recordsPageLink(string $file, string $role, array $extra = []): ?string
{
    $query = recordsQuery(['role' => $role] + $extra);

    if (is_file(dirname(__DIR__) . '/' . $file)) {
        return '../' . $file . $query;
    }

    $demo = pathinfo($file, PATHINFO_FILENAME) . '-test.php';

    return is_file(__DIR__ . '/' . $demo) ? $demo . $query : null;
}

function recordsFormatDate(string $date): string
{
    return (new DateTimeImmutable($date))->format('j M Y');
}

function recordsRelativeDay(string $date, DateTimeImmutable $today): string
{
    $days = (int) $today->diff(new DateTimeImmutable($date))->format('%r%a');

    if ($days === 0) {
        return 'today';
    }
    if ($days === -1) {
        return 'yesterday';
    }
    if ($days === 1) {
        return 'tomorrow';
    }

    return $days < 0 ? abs($days) . ' days ago' : 'in ' . $days . ' days';
}

function recordsBadge(string $value, array $map): string
{
    $item = $map[$value] ?? ['label' => $value, 'badge' => 'neutral'];

    return '<span class="badge badge--' . recordsEscape($item['badge']) . '">' . recordsEscape($item['label']) . '</span>';
}

function recordsTierBadge(string $tier, array $tiers): string
{
    if (!isset($tiers[$tier])) {
        return '';
    }

    return '<span class="tier-badge tier-badge--' . recordsEscape($tier) . '" data-records-tier>'
        . recordsEscape($tiers[$tier]['label'] . ' · ' . $tiers[$tier]['note'])
        . '</span>';
}

$recordsUsers = [
    'patient'          => ['name' => 'Elif Arslan',       'patient_id' => 1],
    'doctor'           => ['name' => 'Dr. Andreas Kyrou', 'doctor_id' => 11, 'department_id' => 1],
    'healthcare_staff' => ['name' => 'Zeynep Şahin',      'hospital_name' => 'Nicosia State Hospital'],
    'admin'            => ['name' => 'Nikos Demetriou'],
];
$recordsUser = $recordsUsers[$recordsRole];

$recordsDepartments = [
    1 => ['department_name' => 'Cardiology',        'hospital_name' => 'Nicosia State Hospital'],
    2 => ['department_name' => 'Dermatology',       'hospital_name' => 'Nicosia State Hospital'],
    3 => ['department_name' => 'Internal Medicine', 'hospital_name' => 'Nicosia State Hospital'],
    4 => ['department_name' => 'Pediatrics',        'hospital_name' => 'Nicosia State Hospital'],
    5 => ['department_name' => 'Neurology',         'hospital_name' => 'Nicosia State Hospital'],
    6 => ['department_name' => 'Cardiology',        'hospital_name' => 'Famagusta State Hospital'],
];

$recordsDoctors = [
    11 => ['doctor_name' => 'Dr. Andreas Kyrou',   'department_id' => 1],
    12 => ['doctor_name' => 'Dr. Eleni Markou',    'department_id' => 1],
    13 => ['doctor_name' => 'Dr. Selin Aydın',     'department_id' => 2],
    14 => ['doctor_name' => 'Dr. Can Öztürk',      'department_id' => 3],
    15 => ['doctor_name' => 'Dr. Elena Costa',     'department_id' => 4],
    16 => ['doctor_name' => 'Dr. Mert Kaya',       'department_id' => 5],
    17 => ['doctor_name' => 'Dr. Sophia Pavlidou', 'department_id' => 6],
];

$recordsPatients = [
    1  => ['patient_name' => 'Elif Arslan',      'birth_date' => '1991-04-12'],
    2  => ['patient_name' => 'Maria Kouppa',     'birth_date' => '1958-09-03'],
    3  => ['patient_name' => 'Mehmet Yıldız',    'birth_date' => '1966-01-21'],
    4  => ['patient_name' => 'Andreas Georgiou', 'birth_date' => '1958-06-30'],
    5  => ['patient_name' => 'Ayşe Demir',       'birth_date' => '1979-11-08'],
    6  => ['patient_name' => 'Can Yılmaz',       'birth_date' => '1995-02-17'],
    7  => ['patient_name' => 'Lefteris Pavlou',  'birth_date' => '1983-07-25'],
    8  => ['patient_name' => 'Deniz Kara',       'birth_date' => '2017-05-14'],
    9  => ['patient_name' => 'Hasan Çelik',      'birth_date' => '1972-03-09'],
    10 => ['patient_name' => 'Sophia Ioannou',   'birth_date' => '1988-12-01'],
];

$recordsCases = [
    1011 => ['patient_id' => 1,  'department_id' => 3, 'doctor_id' => 14,   'tier_color' => 'green',  'case_state' => 'closed', 'created_at' => recordsDate(-40), 'closed_at' => recordsDate(-12), 'closed_by_doctor_id' => 14, 'case_description' => 'Routine check-up, no further visits needed.'],
    1033 => ['patient_id' => 9,  'department_id' => 1, 'doctor_id' => 11,   'tier_color' => 'yellow', 'case_state' => 'closed', 'created_at' => recordsDate(-60), 'closed_at' => recordsDate(-3),  'closed_by_doctor_id' => 11, 'case_description' => 'Medication adjusted, condition stable.'],
    1038 => ['patient_id' => 1,  'department_id' => 2, 'doctor_id' => 13,   'tier_color' => 'yellow', 'case_state' => 'open',   'created_at' => recordsDate(-25), 'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Skin rash on both arms, topical treatment started.'],
    1042 => ['patient_id' => 1,  'department_id' => 1, 'doctor_id' => 11,   'tier_color' => 'orange', 'case_state' => 'open',   'created_at' => recordsDate(-45), 'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Blood pressure monitoring and medication review.'],
    1049 => ['patient_id' => 4,  'department_id' => 1, 'doctor_id' => 11,   'tier_color' => 'green',  'case_state' => 'open',   'created_at' => recordsDate(-20), 'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Post-operative check, recovering well.'],
    1051 => ['patient_id' => 3,  'department_id' => 1, 'doctor_id' => 11,   'tier_color' => 'yellow', 'case_state' => 'open',   'created_at' => recordsDate(-14), 'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Irregular heartbeat, 24-hour Holter test requested.'],
    1056 => ['patient_id' => 2,  'department_id' => 1, 'doctor_id' => 11,   'tier_color' => 'orange', 'case_state' => 'open',   'created_at' => recordsDate(-10), 'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Chest pain under observation, ECG scheduled.'],
    1060 => ['patient_id' => 5,  'department_id' => 1, 'doctor_id' => 11,   'tier_color' => 'yellow', 'case_state' => 'open',   'created_at' => recordsDate(-8),  'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Shortness of breath on exertion, echocardiogram planned.'],
    1063 => ['patient_id' => 6,  'department_id' => 2, 'doctor_id' => 13,   'tier_color' => 'green',  'case_state' => 'open',   'created_at' => recordsDate(-15), 'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Acne treatment follow-up.'],
    1065 => ['patient_id' => 7,  'department_id' => 3, 'doctor_id' => 14,   'tier_color' => 'yellow', 'case_state' => 'open',   'created_at' => recordsDate(-9),  'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Persistent fatigue, blood tests ordered.'],
    1067 => ['patient_id' => 8,  'department_id' => 4, 'doctor_id' => 15,   'tier_color' => 'green',  'case_state' => 'open',   'created_at' => recordsDate(-30), 'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Routine growth and vaccination check.'],
    1070 => ['patient_id' => 10, 'department_id' => 5, 'doctor_id' => 16,   'tier_color' => 'yellow', 'case_state' => 'open',   'created_at' => recordsDate(-5),  'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Recurring migraines, MRI requested.'],
    1072 => ['patient_id' => 9,  'department_id' => 6, 'doctor_id' => 17,   'tier_color' => 'green',  'case_state' => 'open',   'created_at' => recordsDate(-4),  'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Annual cardiology check.'],
    1074 => ['patient_id' => 6,  'department_id' => 1, 'doctor_id' => null, 'tier_color' => 'green',  'case_state' => 'open',   'created_at' => recordsDate(-1),  'closed_at' => null, 'closed_by_doctor_id' => null, 'case_description' => 'Palpitations after exercise for two weeks. Opened by the patient.'],
];

$recordsDiagnoses = [
    ['diagnosis_id' => 301, 'case_id' => 1011, 'doctor_id' => 14, 'diagnosis_text' => 'General medical examination, no abnormal findings.', 'icd_code' => 'Z00.0', 'diagnosed_at' => recordsDate(-40)],
    ['diagnosis_id' => 305, 'case_id' => 1033, 'doctor_id' => 11, 'diagnosis_text' => 'Atrial fibrillation, controlled with medication.',     'icd_code' => 'I48.9', 'diagnosed_at' => recordsDate(-59)],
    ['diagnosis_id' => 311, 'case_id' => 1042, 'doctor_id' => 11, 'diagnosis_text' => 'Essential (primary) hypertension.',                    'icd_code' => 'I10',   'diagnosed_at' => recordsDate(-44)],
    ['diagnosis_id' => 318, 'case_id' => 1042, 'doctor_id' => 11, 'diagnosis_text' => 'Hypertensive heart disease without heart failure.',    'icd_code' => 'I11.9', 'diagnosed_at' => recordsDate(-30)],
    ['diagnosis_id' => 320, 'case_id' => 1038, 'doctor_id' => 13, 'diagnosis_text' => 'Contact dermatitis, cause not yet identified.',         'icd_code' => 'L25.9', 'diagnosed_at' => recordsDate(-24)],
    ['diagnosis_id' => 324, 'case_id' => 1049, 'doctor_id' => 11, 'diagnosis_text' => 'Follow-up examination after surgery.',                 'icd_code' => 'Z09',   'diagnosed_at' => recordsDate(-20)],
    ['diagnosis_id' => 327, 'case_id' => 1051, 'doctor_id' => 11, 'diagnosis_text' => 'Cardiac arrhythmia, type to be confirmed by Holter.',  'icd_code' => 'I49.9', 'diagnosed_at' => recordsDate(-13)],
    ['diagnosis_id' => 329, 'case_id' => 1063, 'doctor_id' => 13, 'diagnosis_text' => 'Acne vulgaris.',                                      'icd_code' => 'L70.0', 'diagnosed_at' => recordsDate(-15)],
    ['diagnosis_id' => 331, 'case_id' => 1056, 'doctor_id' => 11, 'diagnosis_text' => 'Chest pain, cause under investigation.',              'icd_code' => 'R07.4', 'diagnosed_at' => recordsDate(-10)],
    ['diagnosis_id' => 333, 'case_id' => 1065, 'doctor_id' => 14, 'diagnosis_text' => 'Fatigue, blood test results pending.',                'icd_code' => 'R53',   'diagnosed_at' => recordsDate(-9)],
    ['diagnosis_id' => 336, 'case_id' => 1067, 'doctor_id' => 15, 'diagnosis_text' => 'Routine child health examination.',                    'icd_code' => 'Z00.1', 'diagnosed_at' => recordsDate(-30)],
    ['diagnosis_id' => 338, 'case_id' => 1070, 'doctor_id' => 16, 'diagnosis_text' => 'Migraine without aura.',                              'icd_code' => 'G43.0', 'diagnosed_at' => recordsDate(-5)],
];

$recordsTreatments = [
    ['treatment_id' => 401, 'case_id' => 1033, 'doctor_id' => 11, 'treatment_desc' => 'Bisoprolol 2.5 mg once daily.',                   'treatment_date' => recordsDate(-59), 'treatment_status' => 'inactive', 'completed_at' => recordsDate(-3)],
    ['treatment_id' => 410, 'case_id' => 1042, 'doctor_id' => 11, 'treatment_desc' => 'Lisinopril 10 mg once daily.',                    'treatment_date' => recordsDate(-44), 'treatment_status' => 'inactive', 'completed_at' => recordsDate(-30)],
    ['treatment_id' => 411, 'case_id' => 1042, 'doctor_id' => 11, 'treatment_desc' => 'Low-salt diet and a daily blood pressure log.',   'treatment_date' => recordsDate(-44), 'treatment_status' => 'active',   'completed_at' => null],
    ['treatment_id' => 415, 'case_id' => 1042, 'doctor_id' => 11, 'treatment_desc' => 'Amlodipine 5 mg once daily.',                     'treatment_date' => recordsDate(-30), 'treatment_status' => 'active',   'completed_at' => null],
    ['treatment_id' => 420, 'case_id' => 1038, 'doctor_id' => 13, 'treatment_desc' => 'Hydrocortisone 1% cream twice daily for 14 days.', 'treatment_date' => recordsDate(-24), 'treatment_status' => 'active',   'completed_at' => null],
    ['treatment_id' => 424, 'case_id' => 1049, 'doctor_id' => 11, 'treatment_desc' => 'Wound care and dressing change every two days.',  'treatment_date' => recordsDate(-20), 'treatment_status' => 'inactive', 'completed_at' => recordsDate(-6)],
    ['treatment_id' => 427, 'case_id' => 1051, 'doctor_id' => 11, 'treatment_desc' => '24-hour Holter monitoring.',                      'treatment_date' => recordsDate(-13), 'treatment_status' => 'active',   'completed_at' => null],
    ['treatment_id' => 429, 'case_id' => 1063, 'doctor_id' => 13, 'treatment_desc' => 'Topical retinoid gel every night.',               'treatment_date' => recordsDate(-15), 'treatment_status' => 'active',   'completed_at' => null],
    ['treatment_id' => 431, 'case_id' => 1056, 'doctor_id' => 11, 'treatment_desc' => 'Aspirin 100 mg once daily.',                      'treatment_date' => recordsDate(-10), 'treatment_status' => 'active',   'completed_at' => null],
    ['treatment_id' => 438, 'case_id' => 1070, 'doctor_id' => 16, 'treatment_desc' => 'Sumatriptan 50 mg when a migraine starts.',       'treatment_date' => recordsDate(-5),  'treatment_status' => 'active',   'completed_at' => null],
];

$recordsAppointments = [
    ['appointment_id' => 480, 'case_id' => 1038, 'doctor_id' => 13, 'appointment_date' => recordsDate(-20), 'appointment_time' => '15:30:00', 'appointment_status' => 'cancelled'],
    ['appointment_id' => 489, 'case_id' => 1042, 'doctor_id' => 11, 'appointment_date' => recordsDate(-30), 'appointment_time' => '11:15:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 497, 'case_id' => 1011, 'doctor_id' => 14, 'appointment_date' => recordsDate(-12), 'appointment_time' => '09:00:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 501, 'case_id' => 1042, 'doctor_id' => 11, 'appointment_date' => recordsDate(2),   'appointment_time' => '10:30:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 502, 'case_id' => 1038, 'doctor_id' => 13, 'appointment_date' => recordsDate(9),   'appointment_time' => '14:00:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 585, 'case_id' => 1049, 'doctor_id' => 11, 'appointment_date' => recordsDate(-6),  'appointment_time' => '10:00:00', 'appointment_status' => 'cancelled'],
    ['appointment_id' => 590, 'case_id' => 1033, 'doctor_id' => 11, 'appointment_date' => recordsDate(-3),  'appointment_time' => '14:00:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 608, 'case_id' => 1056, 'doctor_id' => 11, 'appointment_date' => recordsDate(0),   'appointment_time' => '09:00:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 609, 'case_id' => 1051, 'doctor_id' => 11, 'appointment_date' => recordsDate(0),   'appointment_time' => '10:30:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 610, 'case_id' => 1049, 'doctor_id' => 11, 'appointment_date' => recordsDate(0),   'appointment_time' => '13:15:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 611, 'case_id' => 1060, 'doctor_id' => 11, 'appointment_date' => recordsDate(0),   'appointment_time' => '15:00:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 615, 'case_id' => 1056, 'doctor_id' => 11, 'appointment_date' => recordsDate(5),   'appointment_time' => '09:30:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 616, 'case_id' => 1051, 'doctor_id' => 11, 'appointment_date' => recordsDate(7),   'appointment_time' => '11:00:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 618, 'case_id' => 1063, 'doctor_id' => 13, 'appointment_date' => recordsDate(-2),  'appointment_time' => '10:30:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 620, 'case_id' => 1063, 'doctor_id' => 13, 'appointment_date' => recordsDate(0),   'appointment_time' => '09:30:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 621, 'case_id' => 1065, 'doctor_id' => 14, 'appointment_date' => recordsDate(0),   'appointment_time' => '11:00:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 622, 'case_id' => 1067, 'doctor_id' => 15, 'appointment_date' => recordsDate(0),   'appointment_time' => '14:30:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 624, 'case_id' => 1065, 'doctor_id' => 14, 'appointment_date' => recordsDate(3),   'appointment_time' => '09:00:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 625, 'case_id' => 1070, 'doctor_id' => 16, 'appointment_date' => recordsDate(4),   'appointment_time' => '13:30:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 630, 'case_id' => 1072, 'doctor_id' => 17, 'appointment_date' => recordsDate(0),   'appointment_time' => '10:00:00', 'appointment_status' => 'confirmed'],
];

$recordsStaffDepartments = array_filter(
    $recordsDepartments,
    fn (array $item): bool => $item['hospital_name'] === $recordsUsers['healthcare_staff']['hospital_name']
);

$recordsScope = array_filter($recordsCases, function (array $case) use ($recordsRole, $recordsUser, $recordsStaffDepartments): bool {
    if ($recordsRole === 'patient') {
        return $case['patient_id'] === $recordsUser['patient_id'];
    }
    if ($recordsRole === 'doctor') {
        return $case['department_id'] === $recordsUser['department_id'];
    }
    if ($recordsRole === 'healthcare_staff') {
        return isset($recordsStaffDepartments[$case['department_id']]);
    }

    return false;
});

foreach ($recordsScope as $recordsCaseId => $recordsCase) {
    $recordsPatient = $recordsPatients[$recordsCase['patient_id']];
    $recordsDepartment = $recordsDepartments[$recordsCase['department_id']];
    $recordsByCase = fn (array $item): bool => $item['case_id'] === $recordsCaseId;

    $recordsCaseDiagnoses = array_values(array_filter($recordsDiagnoses, $recordsByCase));
    usort($recordsCaseDiagnoses, fn (array $a, array $b): int => [$b['diagnosed_at'], $b['diagnosis_id']] <=> [$a['diagnosed_at'], $a['diagnosis_id']]);

    $recordsCaseTreatments = array_values(array_filter($recordsTreatments, $recordsByCase));
    usort($recordsCaseTreatments, fn (array $a, array $b): int => [$a['treatment_status'] === 'active' ? 0 : 1, $b['treatment_date'], $b['treatment_id']]
        <=> [$b['treatment_status'] === 'active' ? 0 : 1, $a['treatment_date'], $a['treatment_id']]);

    $recordsCaseAppointments = array_values(array_filter($recordsAppointments, $recordsByCase));
    usort($recordsCaseAppointments, fn (array $a, array $b): int => [$b['appointment_date'], $b['appointment_time']] <=> [$a['appointment_date'], $a['appointment_time']]);

    $recordsScope[$recordsCaseId] += [
        'case_id'           => $recordsCaseId,
        'patient_name'      => $recordsPatient['patient_name'],
        'patient_age'       => (new DateTimeImmutable($recordsPatient['birth_date']))->diff($recordsToday)->y,
        'department_name'   => $recordsDepartment['department_name'],
        'hospital_name'     => $recordsDepartment['hospital_name'],
        'doctor_name'       => $recordsCase['doctor_id'] !== null ? $recordsDoctors[$recordsCase['doctor_id']]['doctor_name'] : null,
        'closed_by_name'    => $recordsCase['closed_by_doctor_id'] !== null ? $recordsDoctors[$recordsCase['closed_by_doctor_id']]['doctor_name'] : null,
        'diagnoses'         => $recordsCaseDiagnoses,
        'treatments'        => $recordsCaseTreatments,
        'appointments'      => $recordsCaseAppointments,
        'active_treatments' => count(array_filter($recordsCaseTreatments, fn (array $item): bool => $item['treatment_status'] === 'active')),
    ];
}

$recordsState = isset($_GET['state']) && is_string($_GET['state']) && isset($recordsStates[$_GET['state']])
    ? $_GET['state']
    : 'open';

$recordsFilterPatient = '';
$recordsFilterTier = null;
$recordsFilterDepartment = null;

if ($recordsRole === 'doctor' || $recordsRole === 'healthcare_staff') {
    $recordsFilterPatient = isset($_GET['patient_name']) && is_string($_GET['patient_name'])
        ? mb_substr(trim(preg_replace('/\s+/u', ' ', $_GET['patient_name']) ?? ''), 0, 100)
        : '';
    $recordsFilterTier = isset($_GET['tier']) && is_string($_GET['tier']) && isset($recordsTiers[$_GET['tier']])
        ? $_GET['tier']
        : null;
}

if ($recordsRole === 'healthcare_staff') {
    $recordsFilterDepartment = isset($_GET['department_id']) && is_string($_GET['department_id'])
        && ctype_digit($_GET['department_id']) && isset($recordsStaffDepartments[(int) $_GET['department_id']])
        ? (int) $_GET['department_id']
        : null;
}

$recordsKeepFilters = array_filter([
    'patient_name'  => $recordsFilterPatient,
    'tier'          => $recordsFilterTier,
    'department_id' => $recordsFilterDepartment,
], fn ($value): bool => $value !== null && $value !== '');

$recordsMatching = array_filter($recordsScope, function (array $case) use ($recordsFilterPatient, $recordsFilterTier, $recordsFilterDepartment): bool {
    if ($recordsFilterPatient !== '' && mb_stripos($case['patient_name'], $recordsFilterPatient) === false) {
        return false;
    }
    if ($recordsFilterTier !== null && $case['tier_color'] !== $recordsFilterTier) {
        return false;
    }
    if ($recordsFilterDepartment !== null && $case['department_id'] !== $recordsFilterDepartment) {
        return false;
    }

    return true;
});

$recordsStateCounts = ['open' => 0, 'closed' => 0];
foreach ($recordsMatching as $recordsCase) {
    $recordsStateCounts[$recordsCase['case_state']] += 1;
}

$recordsList = array_values(array_filter($recordsMatching, fn (array $case): bool => $case['case_state'] === $recordsState));

usort($recordsList, function (array $a, array $b) use ($recordsRole, $recordsTiers): int {
    if ($a['case_state'] === 'closed') {
        return $b['closed_at'] <=> $a['closed_at'];
    }
    if ($recordsRole === 'patient') {
        return $b['created_at'] <=> $a['created_at'];
    }

    return [$recordsTiers[$a['tier_color']]['rank'], $b['created_at']] <=> [$recordsTiers[$b['tier_color']]['rank'], $a['created_at']];
});

$recordsSelectedId = isset($_GET['case_id']) && is_string($_GET['case_id']) && ctype_digit($_GET['case_id'])
    && isset($recordsScope[(int) $_GET['case_id']])
    ? (int) $_GET['case_id']
    : ($recordsList[0]['case_id'] ?? null);
$recordsSelected = $recordsSelectedId !== null ? $recordsScope[$recordsSelectedId] : null;

$recordsAdd = isset($_GET['add']) && is_string($_GET['add']) && in_array($_GET['add'], ['diagnosis', 'treatment'], true)
    ? $_GET['add']
    : null;

$recordsCanEdit = $recordsRole === 'doctor' && $recordsSelected !== null && $recordsSelected['case_state'] === 'open';

$recordsHeaders = [
    'patient'          => ['title' => 'My medical records', 'subtitle' => 'Your cases with their diagnoses, treatments and visits.'],
    'doctor'           => ['title' => 'Medical records',    'subtitle' => 'Cases of the Cardiology department · Nicosia State Hospital'],
    'healthcare_staff' => ['title' => 'Medical records',    'subtitle' => 'Nicosia State Hospital · all departments · view only'],
    'admin'            => ['title' => 'Medical records',    'subtitle' => 'Medical records are kept by doctors and viewed by patients and healthcare staff.'],
];

$recordsAppointmentsLink = recordsPageLink('appointments.php', $recordsRole);

$chnPageTitle = 'Medical Records';
$chnUserName = $recordsUser['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | Medical Records</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/medical-records.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
    <script src="../js/medical-records.js" defer></script>
</head>
<body>
<?php
$chnUserRole = $recordsRole;
require __DIR__ . '/../include/left-sidebar.php';

$chnUserRole = $recordsRoles[$recordsRole];
require __DIR__ . '/../include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner records">
        <aside class="alert alert--info records-demo" aria-label="Demo notice">
            <p class="records-demo__text"><strong>Demo data.</strong> All names and records are fictional. Preview a role:</p>
            <nav aria-label="Preview role">
                <ul class="records-demo__roles">
                    <?php foreach ($recordsRoles as $recordsRoleKey => $recordsRoleLabel): ?>
                        <li>
                            <a class="records-demo__link"
                               href="<?= recordsEscape(recordsQuery(['role' => $recordsRoleKey])) ?>"
                               <?= $recordsRoleKey === $recordsRole ? 'aria-current="page"' : '' ?>>
                                <?= recordsEscape($recordsRoleLabel) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <div class="page-header">
            <div>
                <h1 class="page-header__title"><?= recordsEscape($recordsHeaders[$recordsRole]['title']) ?></h1>
                <p class="page-header__subtitle"><?= recordsEscape($recordsHeaders[$recordsRole]['subtitle']) ?></p>
            </div>
        </div>

        <p class="alert alert--info records-notice" id="records-notice" role="status" hidden></p>

        <?php if ($recordsRole === 'admin'): ?>
            <section class="card" aria-labelledby="records-admin-title">
                <div class="empty-state">
                    <h2 class="empty-state__title" id="records-admin-title">Not available for admins</h2>
                    <p class="empty-state__text">Admins manage accounts and complaints. Medical records are only visible to the patient, doctors and healthcare staff.</p>
                    <a class="btn btn--outline" href="<?= recordsEscape('dashboard-test.php' . recordsQuery(['role' => 'admin'])) ?>">Back to dashboard</a>
                </div>
            </section>
        <?php else: ?>
            <div class="records-layout">
                <section class="card records-list" aria-labelledby="records-list-title">
                    <header class="card__header records-list__header">
                        <div>
                            <h2 class="card__title" id="records-list-title"><?= $recordsRole === 'patient' ? 'My cases' : 'Cases' ?></h2>
                            <p class="card__subtitle">
                                <?= count($recordsList) ?> <?= recordsEscape(strtolower($recordsStates[$recordsState]['label'])) ?>
                                <?= count($recordsList) === 1 ? 'case' : 'cases' ?><?= $recordsKeepFilters !== [] ? ' · Filtered' : '' ?>
                            </p>
                        </div>
                        <nav aria-label="Case state">
                            <ul class="records-tabs">
                                <?php foreach ($recordsStates as $recordsStateKey => $recordsStateItem): ?>
                                    <li>
                                        <a class="records-tabs__link"
                                           href="<?= recordsEscape(recordsQuery(['role' => $recordsRole, 'state' => $recordsStateKey] + $recordsKeepFilters)) ?>"
                                           <?= $recordsStateKey === $recordsState ? 'aria-current="page"' : '' ?>>
                                            <?= recordsEscape($recordsStateItem['label']) ?> (<?= (int) $recordsStateCounts[$recordsStateKey] ?>)
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </nav>
                    </header>

                    <?php if ($recordsRole !== 'patient'): ?>
                        <form class="records-filters" method="get" action="" aria-label="Filter cases">
                            <input type="hidden" name="role" value="<?= recordsEscape($recordsRole) ?>">
                            <input type="hidden" name="state" value="<?= recordsEscape($recordsState) ?>">
                            <div class="form-group">
                                <label class="form-label" for="records-filter-patient">Patient name</label>
                                <input type="search" class="form-control" id="records-filter-patient" name="patient_name"
                                       value="<?= recordsEscape($recordsFilterPatient) ?>" maxlength="100"
                                       autocomplete="off" placeholder="Search by name">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="records-filter-tier">Tier</label>
                                <select class="form-control" id="records-filter-tier" name="tier">
                                    <option value="">All tiers</option>
                                    <?php foreach ($recordsTiers as $recordsTierKey => $recordsTier): ?>
                                        <option value="<?= recordsEscape($recordsTierKey) ?>" <?= $recordsFilterTier === $recordsTierKey ? 'selected' : '' ?>>
                                            <?= recordsEscape($recordsTier['label'] . ' · ' . $recordsTier['note']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($recordsRole === 'healthcare_staff'): ?>
                                <div class="form-group">
                                    <label class="form-label" for="records-filter-department">Department</label>
                                    <select class="form-control" id="records-filter-department" name="department_id">
                                        <option value="">All departments</option>
                                        <?php foreach ($recordsStaffDepartments as $recordsDepartmentId => $recordsDepartment): ?>
                                            <option value="<?= (int) $recordsDepartmentId ?>" <?= $recordsFilterDepartment === $recordsDepartmentId ? 'selected' : '' ?>>
                                                <?= recordsEscape($recordsDepartment['department_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="records-filters__actions">
                                <button type="submit" class="btn btn--outline">Filter</button>
                                <?php if ($recordsKeepFilters !== []): ?>
                                    <a class="btn btn--outline" href="<?= recordsEscape(recordsQuery(['role' => $recordsRole, 'state' => $recordsState])) ?>">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($recordsList === []): ?>
                        <div class="empty-state records-list__empty">
                            <?php if ($recordsKeepFilters !== []): ?>
                                <p class="empty-state__title">No matching cases</p>
                                <p class="empty-state__text">Try another name or tier, or clear the filters.</p>
                            <?php elseif ($recordsState === 'closed'): ?>
                                <p class="empty-state__title">No closed cases</p>
                                <p class="empty-state__text">Cases closed by a doctor will appear here.</p>
                            <?php else: ?>
                                <p class="empty-state__title">No open cases</p>
                                <p class="empty-state__text">
                                    <?= $recordsRole === 'patient' ? 'New cases are opened on the Medical Cases page.' : 'New cases will appear here.' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <ul class="records-cases">
                            <?php foreach ($recordsList as $recordsItem): ?>
                                <li>
                                    <a class="records-case"
                                       href="<?= recordsEscape(recordsQuery(['role' => $recordsRole, 'state' => $recordsState] + $recordsKeepFilters + ['case_id' => $recordsItem['case_id']]) . '#records-detail') ?>"
                                       data-records-case="<?= (int) $recordsItem['case_id'] ?>"
                                       <?= $recordsItem['case_id'] === $recordsSelectedId ? 'aria-current="true"' : '' ?>>
                                        <span class="records-case__top">
                                            <span class="records-case__id">Case #<?= (int) $recordsItem['case_id'] ?></span>
                                            <?= recordsTierBadge($recordsItem['tier_color'], $recordsTiers) ?>
                                        </span>
                                        <?php if ($recordsRole === 'patient'): ?>
                                            <span class="records-case__name"><?= recordsEscape($recordsItem['department_name']) ?></span>
                                            <span class="records-case__meta"><?= recordsEscape($recordsItem['hospital_name']) ?></span>
                                        <?php else: ?>
                                            <span class="records-case__name"><?= recordsEscape($recordsItem['patient_name']) ?></span>
                                            <span class="records-case__meta">
                                                Age <?= (int) $recordsItem['patient_age'] ?><?= $recordsRole === 'healthcare_staff' ? recordsEscape(' · ' . $recordsItem['department_name']) : '' ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="records-case__meta">
                                            <?php if ($recordsItem['case_state'] === 'closed'): ?>
                                                Closed <?= recordsEscape(recordsRelativeDay($recordsItem['closed_at'], $recordsToday)) ?>
                                            <?php else: ?>
                                                Opened <?= recordsEscape(recordsRelativeDay($recordsItem['created_at'], $recordsToday)) ?>
                                            <?php endif; ?>
                                            · <?= count($recordsItem['diagnoses']) ?> <?= count($recordsItem['diagnoses']) === 1 ? 'diagnosis' : 'diagnoses' ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <?php if ($recordsSelected === null): ?>
                    <section class="card records-detail" id="records-detail" aria-label="Case details">
                        <div class="empty-state">
                            <p class="empty-state__title">No case selected</p>
                            <p class="empty-state__text">Choose a case from the list to see its records.</p>
                        </div>
                    </section>
                <?php else: ?>
                    <?php $recordsCaseLabel = 'Case #' . $recordsSelected['case_id']; ?>
                    <section class="card records-detail" id="records-detail" aria-labelledby="records-detail-title"
                             data-case-id="<?= (int) $recordsSelected['case_id'] ?>"
                             data-doctor-name="<?= recordsEscape($recordsRole === 'doctor' ? $recordsUser['name'] : '') ?>">
                        <header class="card__header records-detail__header">
                            <div>
                                <h2 class="card__title records-detail__title" id="records-detail-title">
                                    <?= recordsEscape($recordsCaseLabel) ?>
                                    <?= recordsTierBadge($recordsSelected['tier_color'], $recordsTiers) ?>
                                    <span data-records-state><?= recordsBadge($recordsSelected['case_state'], $recordsStates) ?></span>
                                </h2>
                                <p class="card__subtitle">
                                    <?= recordsEscape($recordsSelected['department_name'] . ' · ' . $recordsSelected['hospital_name']) ?>
                                </p>
                            </div>
                            <?php if ($recordsRole === 'patient' && $recordsSelected['case_state'] === 'open' && $recordsAppointmentsLink !== null): ?>
                                <a class="btn btn--primary btn--sm" href="<?= recordsEscape($recordsAppointmentsLink) ?>">Book appointment</a>
                            <?php endif; ?>
                        </header>

                        <dl class="records-facts">
                            <?php if ($recordsRole !== 'patient'): ?>
                                <div class="records-facts__item">
                                    <dt>Patient</dt>
                                    <dd><?= recordsEscape($recordsSelected['patient_name']) ?> · Age <?= (int) $recordsSelected['patient_age'] ?></dd>
                                </div>
                            <?php endif; ?>
                            <div class="records-facts__item">
                                <dt>Responsible doctor</dt>
                                <dd><?= recordsEscape($recordsSelected['doctor_name'] ?? 'Not assigned yet') ?></dd>
                            </div>
                            <div class="records-facts__item">
                                <dt>Opened</dt>
                                <dd><?= recordsEscape(recordsFormatDate($recordsSelected['created_at'])) ?></dd>
                            </div>
                            <?php if ($recordsSelected['case_state'] === 'closed'): ?>
                                <div class="records-facts__item">
                                    <dt>Closed</dt>
                                    <dd><?= recordsEscape(recordsFormatDate($recordsSelected['closed_at']) . ' by ' . $recordsSelected['closed_by_name']) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <div class="records-description">
                            <p class="records-description__label">Case description</p>
                            <p class="records-description__text"><?= recordsEscape($recordsSelected['case_description']) ?></p>
                        </div>

                        <?php if ($recordsCanEdit): ?>
                            <div class="records-tools" data-records-editable>
                                <form class="records-tools__tier" action="../backend/medical-records.php" method="post" data-records-form="tier">
                                    <input type="hidden" name="role" value="<?= recordsEscape($recordsRole) ?>">
                                    <input type="hidden" name="action" value="set_tier">
                                    <input type="hidden" name="case_id" value="<?= (int) $recordsSelected['case_id'] ?>">
                                    <div class="form-group">
                                        <label class="form-label" for="records-tier">Tier</label>
                                        <select class="form-control" id="records-tier" name="tier_color">
                                            <?php foreach ($recordsTiers as $recordsTierKey => $recordsTier): ?>
                                                <option value="<?= recordsEscape($recordsTierKey) ?>" <?= $recordsSelected['tier_color'] === $recordsTierKey ? 'selected' : '' ?>>
                                                    <?= recordsEscape($recordsTier['label'] . ' · ' . $recordsTier['note']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn--outline">Save tier</button>
                                </form>

                                <form class="records-tools__close" action="../backend/medical-records.php" method="post" data-records-form="close">
                                    <input type="hidden" name="role" value="<?= recordsEscape($recordsRole) ?>">
                                    <input type="hidden" name="action" value="close_case">
                                    <input type="hidden" name="case_id" value="<?= (int) $recordsSelected['case_id'] ?>">
                                    <button type="submit" class="btn btn--danger" id="records-close"
                                            aria-describedby="records-close-hint"
                                            <?= $recordsSelected['active_treatments'] > 0 ? 'disabled' : '' ?>>Close case</button>
                                    <p class="form-hint records-tools__hint" id="records-close-hint">
                                        <?= $recordsSelected['active_treatments'] > 0
                                            ? 'Mark every active treatment inactive before closing the case.'
                                            : 'No active treatments. The case can be closed.' ?>
                                    </p>
                                </form>
                            </div>
                        <?php elseif ($recordsRole === 'doctor'): ?>
                            <p class="alert alert--info records-readonly">This case is closed. Closed cases are read-only.</p>
                        <?php endif; ?>

                        <section class="records-section" id="records-diagnoses" aria-labelledby="records-diagnoses-title">
                            <h3 class="records-section__title" id="records-diagnoses-title">
                                Diagnoses <span class="records-section__count" data-records-count="diagnoses">(<?= count($recordsSelected['diagnoses']) ?>)</span>
                            </h3>
                            <p class="records-empty" data-records-empty="diagnoses" <?= $recordsSelected['diagnoses'] !== [] ? 'hidden' : '' ?>>No diagnosis yet.</p>
                            <ul class="records-entries" data-records-list="diagnoses">
                                <?php foreach ($recordsSelected['diagnoses'] as $recordsDiagnosis): ?>
                                    <li class="records-entry">
                                        <div class="records-entry__main">
                                            <p class="records-entry__title"><?= recordsEscape($recordsDiagnosis['diagnosis_text']) ?></p>
                                            <p class="records-entry__meta">
                                                <?= recordsEscape($recordsDoctors[$recordsDiagnosis['doctor_id']]['doctor_name'] . ' · ' . recordsFormatDate($recordsDiagnosis['diagnosed_at'])) ?>
                                            </p>
                                        </div>
                                        <?php if ($recordsDiagnosis['icd_code'] !== null): ?>
                                            <span class="records-code" title="ICD-10 code">ICD-10 <?= recordsEscape($recordsDiagnosis['icd_code']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ($recordsCanEdit): ?>
                                <details class="records-add" data-records-editable <?= $recordsAdd === 'diagnosis' ? 'open' : '' ?>>
                                    <summary class="btn btn--outline btn--sm records-add__toggle">
                                        <span class="records-add__open">Add diagnosis</span>
                                        <span class="records-add__close">Cancel</span>
                                    </summary>
                                    <form class="records-add__form" action="../backend/medical-records.php" method="post" data-records-form="diagnosis" novalidate>
                                        <input type="hidden" name="role" value="<?= recordsEscape($recordsRole) ?>">
                                        <input type="hidden" name="action" value="add_diagnosis">
                                        <input type="hidden" name="case_id" value="<?= (int) $recordsSelected['case_id'] ?>">
                                        <div class="form-group">
                                            <label class="form-label" for="records-diagnosis-text">Diagnosis</label>
                                            <textarea class="form-control" id="records-diagnosis-text" name="diagnosis_text" rows="3" maxlength="1000" required></textarea>
                                            <p class="form-error" id="records-diagnosis-text-error" hidden></p>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="records-diagnosis-icd">ICD-10 code (optional)</label>
                                            <input type="text" class="form-control records-add__short" id="records-diagnosis-icd" name="icd_code"
                                                   maxlength="8" autocomplete="off" spellcheck="false" placeholder="e.g. I10" aria-describedby="records-diagnosis-icd-hint">
                                            <p class="form-hint" id="records-diagnosis-icd-hint">A letter and two digits, optionally followed by a dot and more (I10, I11.9).</p>
                                            <p class="form-error" id="records-diagnosis-icd-error" hidden></p>
                                        </div>
                                        <button type="submit" class="btn btn--primary">Save diagnosis</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </section>

                        <section class="records-section" id="records-treatments" aria-labelledby="records-treatments-title">
                            <h3 class="records-section__title" id="records-treatments-title">
                                Treatments <span class="records-section__count" data-records-count="treatments">(<?= count($recordsSelected['treatments']) ?>)</span>
                            </h3>
                            <p class="records-empty" data-records-empty="treatments" <?= $recordsSelected['treatments'] !== [] ? 'hidden' : '' ?>>No treatment yet.</p>
                            <ul class="records-entries" data-records-list="treatments">
                                <?php foreach ($recordsSelected['treatments'] as $recordsTreatment): ?>
                                    <li class="records-entry" data-treatment-status="<?= recordsEscape($recordsTreatment['treatment_status']) ?>">
                                        <div class="records-entry__main">
                                            <p class="records-entry__title"><?= recordsEscape($recordsTreatment['treatment_desc']) ?></p>
                                            <p class="records-entry__meta" data-records-treatment-meta>
                                                <?= recordsEscape($recordsDoctors[$recordsTreatment['doctor_id']]['doctor_name'] . ' · Started ' . recordsFormatDate($recordsTreatment['treatment_date'])) ?>
                                                <?= $recordsTreatment['completed_at'] !== null ? recordsEscape(' · Ended ' . recordsFormatDate($recordsTreatment['completed_at'])) : '' ?>
                                            </p>
                                        </div>
                                        <div class="records-entry__side">
                                            <span data-records-treatment-status><?= recordsBadge($recordsTreatment['treatment_status'], $recordsTreatmentStatuses) ?></span>
                                            <?php if ($recordsCanEdit && $recordsTreatment['treatment_status'] === 'active'): ?>
                                                <button type="button" class="btn btn--outline btn--sm" data-records-editable
                                                        data-records-deactivate="<?= (int) $recordsTreatment['treatment_id'] ?>"
                                                        data-records-label="<?= recordsEscape($recordsTreatment['treatment_desc']) ?>">Mark inactive</button>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ($recordsCanEdit): ?>
                                <details class="records-add" data-records-editable <?= $recordsAdd === 'treatment' ? 'open' : '' ?>>
                                    <summary class="btn btn--outline btn--sm records-add__toggle">
                                        <span class="records-add__open">Add treatment</span>
                                        <span class="records-add__close">Cancel</span>
                                    </summary>
                                    <form class="records-add__form" action="../backend/medical-records.php" method="post" data-records-form="treatment" novalidate>
                                        <input type="hidden" name="role" value="<?= recordsEscape($recordsRole) ?>">
                                        <input type="hidden" name="action" value="add_treatment">
                                        <input type="hidden" name="case_id" value="<?= (int) $recordsSelected['case_id'] ?>">
                                        <div class="form-group">
                                            <label class="form-label" for="records-treatment-desc">Treatment</label>
                                            <textarea class="form-control" id="records-treatment-desc" name="treatment_desc" rows="3" maxlength="1000" required
                                                      placeholder="Medicine, dose and frequency, or procedure"></textarea>
                                            <p class="form-error" id="records-treatment-desc-error" hidden></p>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="records-treatment-date">Start date</label>
                                            <input type="date" class="form-control records-add__short" id="records-treatment-date" name="treatment_date" required
                                                   value="<?= recordsEscape($recordsTodayKey) ?>"
                                                   min="<?= recordsEscape($recordsSelected['created_at']) ?>"
                                                   max="<?= recordsEscape($recordsTodayKey) ?>">
                                            <p class="form-error" id="records-treatment-date-error" hidden></p>
                                        </div>
                                        <button type="submit" class="btn btn--primary">Save treatment</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </section>

                        <section class="records-section" id="records-appointments" aria-labelledby="records-appointments-title">
                            <div class="records-section__header">
                                <h3 class="records-section__title" id="records-appointments-title">
                                    Appointments <span class="records-section__count">(<?= count($recordsSelected['appointments']) ?>)</span>
                                </h3>
                                <?php if ($recordsAppointmentsLink !== null): ?>
                                    <a class="records-section__link" href="<?= recordsEscape($recordsAppointmentsLink) ?>">Open appointments</a>
                                <?php endif; ?>
                            </div>
                            <?php if ($recordsSelected['appointments'] === []): ?>
                                <p class="records-empty">No appointments for this case yet.</p>
                            <?php else: ?>
                                <ul class="records-entries">
                                    <?php foreach ($recordsSelected['appointments'] as $recordsAppointment): ?>
                                        <?php $recordsWhen = new DateTimeImmutable($recordsAppointment['appointment_date'] . ' ' . $recordsAppointment['appointment_time']); ?>
                                        <li class="records-entry">
                                            <div class="records-entry__main">
                                                <p class="records-entry__title">
                                                    <time datetime="<?= recordsEscape($recordsWhen->format('Y-m-d\TH:i')) ?>"><?= recordsEscape($recordsWhen->format('D, j M Y · H:i')) ?></time>
                                                </p>
                                                <p class="records-entry__meta">
                                                    <?= recordsEscape($recordsDoctors[$recordsAppointment['doctor_id']]['doctor_name'] . ' · ' . ucfirst(recordsRelativeDay($recordsAppointment['appointment_date'], $recordsToday))) ?>
                                                </p>
                                            </div>
                                            <div class="records-entry__side">
                                                <?= recordsBadge($recordsAppointment['appointment_status'], $recordsAppointmentStatuses) ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </section>
                    </section>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
