<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:tx_membermanagement_domain_model_payment',
        'label' => 'member',
        'label_alt' => 'paid_at, amount',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'iconfile' => 'EXT:member_management/Resources/Public/Icons/payment.svg',
    ],
    'types' => [
        1 => ['showitem' => 'member, paid_at, amount'],
    ],
    'columns' => [
        'member' => [
            'exclude' => true,
            'label' => 'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:tx_membermanagement_domain_model_payment.member',
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
            'label' => 'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:tx_membermanagement_domain_model_payment.paid_at',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
                'required' => true,
            ],
        ],
        'amount' => [
            'exclude' => true,
            'label' => 'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:tx_membermanagement_domain_model_payment.amount',
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
