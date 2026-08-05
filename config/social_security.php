<?php

return [
    'benin' => [
        // Source: CNSS Bénin, cotisations sociales.
        'pension' => [
            'employee_rate' => 0.036,
            'employer_rate' => 0.064,
        ],
        'family_allowance' => [
            'employer_rate' => 0.09,
        ],
        // The occupational-risk rate depends on the activity of the employer
        // and ranges from 1% to 4%. Keep it configurable per organization.
        'occupational_risk' => [
            'default_employer_rate' => 0.01,
            'min_rate' => 0.01,
            'max_rate' => 0.04,
        ],
    ],
];
