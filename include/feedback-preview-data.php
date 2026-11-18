<?php
/** Fictional UI fixtures only. Never use as a fallback for a failed live request. */
return [
    'user_ids' => ['patient' => 901, 'doctor' => 902, 'healthcare_staff' => 903, 'staff' => 903],
    'feedback' => [
        ['feedback_id' => 701, 'user_id' => 901, 'feedback_msg' => 'Fictional feedback: the case overview is easy to read. Could the date labels be clearer?', 'feedback_date' => '2026-11-10'],
        ['feedback_id' => 702, 'user_id' => 901, 'feedback_msg' => 'Fictional feedback: please make the appointment instructions easier to find.', 'feedback_date' => '2026-11-12'],
        ['feedback_id' => 703, 'user_id' => 902, 'feedback_msg' => 'Fictional feedback: clearer department labels would help when reviewing assigned cases.', 'feedback_date' => '2026-11-11'],
    ],
    'responses' => [
        ['response_id' => 801, 'feedback_id' => 701, 'response_msg' => 'Fictional response: thank you for the suggestion. The date labels will be reviewed during the interface design phase.', 'response_date' => '2026-11-11'],
        ['response_id' => 802, 'feedback_id' => 703, 'response_msg' => 'Fictional response: the department labels have been noted for the next design review.', 'response_date' => '2026-11-12'],
    ],
];
