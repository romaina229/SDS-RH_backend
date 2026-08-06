<?php

return [
    'trial_days' => 14,

    'currencies' => [
        'XOF' => [
            'rate_from_xof' => 1,
            'decimals' => 0,
        ],
        'EUR' => [
            'rate_from_xof' => 1 / 655.957,
            'decimals' => 2,
        ],
        'USD' => [
            'rate_from_xof' => 1 / 580,
            'decimals' => 2,
        ],
    ],

    'plans' => [
        'free' => [
            'db_name' => 'gratuit',
            'min' => 1,
            'max' => 5,
            'price_xof_monthly' => 0,
            'custom_quote' => false,
        ],
        'starter' => [
            'db_name' => 'starter',
            'min' => 6,
            'max' => 20,
            'price_xof_monthly' => 5000,
            'custom_quote' => false,
        ],
        'standard' => [
            'db_name' => 'standard',
            'min' => 21,
            'max' => 50,
            'price_xof_monthly' => 15000,
            'custom_quote' => false,
        ],
        'business' => [
            'db_name' => 'business',
            'min' => 51,
            'max' => 150,
            'price_xof_monthly' => 35000,
            'custom_quote' => false,
        ],
        'enterprise' => [
            'db_name' => 'enterprise',
            'min' => 151,
            'max' => PHP_INT_MAX,
            'price_xof_monthly' => null,
            'custom_quote' => true,
        ],
    ],

    'payment_methods' => [
        'fedapay',
        'kkiapay',
        'card',
        'paypal',
        'transfer',
    ],
];
