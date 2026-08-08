<?php

return [
    // Chaque tenant peut plus tard surcharger ceci via tenant.settings.payslip_items
    'gains' => [
        ['code' => '111N', 'label' => 'SOLDE BRUTE',        'source' => 'base_salary'],
        ['code' => '020N', 'label' => 'IND. RESIDENCE',     'rate_of' => 'base_salary', 'rate' => 0.10],
        ['code' => '202N', 'label' => 'IND. LOGEMENT',      'fixed' => 5000],
        ['code' => '60P018', 'label' => 'PRIME DE RENDEMENT', 'rate_of' => 'base_salary', 'rate' => 0.17],
        ['code' => '60P010', 'label' => 'PRIME SPECIFIQUE', 'rate_of' => 'base_salary', 'rate' => 0.11],
        ['code' => '60P005', 'label' => 'PRIME DE SEDENTARISATION', 'fixed' => 50000],
        ['code' => '8990N', 'label' => 'SURSALAIRE',        'source' => 'bonuses'],
        ['code' => '706N', 'label' => 'ALLOCATION FAMILIALE', 'per_child' => 2500],
    ],
    'retenues' => [
        ['code' => '855C', 'label' => 'ITS', 'source' => 'taxes'],
        ['code' => '830C', 'label' => 'FONDS NAT DE RET PART OUVR', 'source' => 'social_security_employee'],
    ],
    // Charges patronales — affichées pour transparence, non déduites du net
    'patronal' => [
        ['code' => '832C', 'label' => 'VERSEMENT PATRONAL SUR SALAIRE', 'rate_of' => 'base_salary', 'rate' => 0.05],
        ['code' => '856C', 'label' => 'ITS PRIMES PATRONALE', 'rate_of' => 'bonuses', 'rate' => 0.10],
        ['code' => '831C', 'label' => 'FONDS NAT DE RET PART PATRONA', 'source' => 'social_security_employer'],
    ],
];