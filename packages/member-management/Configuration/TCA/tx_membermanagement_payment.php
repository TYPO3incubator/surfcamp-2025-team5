<?php

return [
    'ctrl' => [
        'title' => 'Payment',
        'label' => 'user',
        'label_alt' => 'paid_at, amount',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'iconfile' => 'EXT:member_management/Resources/Public/Icons/payment.svg',
    ],
    'types' => [
        1 => ['showitem' => 'user, paid_at, amount'],
    ],
    'columns' => [
        'user' => [
            'exclude' => true,
            'label' => 'User',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'minitems' => 1,
                'maxitems' => 1,
                'required' => true,
            ],
        ],
        'paid_at' => [
            'exclude' => true,
            'label' => 'Paid at',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
                'required' => true,
            ],
        ],
        'amount' => [
            'exclude' => true,
            'label' => 'Amount',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'double2',
                'default' => 0.00,
                'required' => true,
            ],
        ],
    ],
];
