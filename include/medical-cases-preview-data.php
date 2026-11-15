<?php
/** Isolated fictional UI fixtures. Never use as a fallback for a failed live request. */
return [
    // Fictional scope identifiers for UI illustration, never authorization rules.
    'context' => ['patient_id' => 501, 'doctor_id' => 601, 'staff_department_id' => 1],
    'cases' => [
        ['case_id' => 1001, 'patient_id' => 501, 'patient_name' => 'Demo Patient A', 'doctor_id' => 601, 'department_id' => 1, 'department_name' => 'Internal Medicine', 'doctor_name' => 'Demo Doctor A', 'created_at' => '2026-11-12 09:00:00', 'case_description' => 'Fictional case for demonstrating an ongoing assessment.', 'case_state' => 'open', 'tier_color' => 'yellow'],
        ['case_id' => 1002, 'patient_id' => 501, 'patient_name' => 'Demo Patient A', 'doctor_id' => null, 'department_id' => 2, 'department_name' => 'Orthopedics', 'doctor_name' => null, 'created_at' => '2026-11-10 14:00:00', 'case_description' => 'Fictional case demonstrating an unassigned responsible doctor.', 'case_state' => 'open', 'tier_color' => 'orange'],
        ['case_id' => 1003, 'patient_id' => 502, 'patient_name' => 'Demo Patient B', 'doctor_id' => 601, 'department_id' => 1, 'department_name' => 'Internal Medicine', 'doctor_name' => 'Demo Doctor A', 'created_at' => '2026-10-20 10:00:00', 'case_description' => 'Fictional case for demonstrating the closed case view.', 'case_state' => 'closed', 'tier_color' => 'green'],
    ],
    // No diagnosis records are fabricated. The view supports multiple records per case.
    'diagnoses' => [],
];
