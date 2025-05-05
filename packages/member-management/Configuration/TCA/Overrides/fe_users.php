<?php

defined('TYPO3') || die();

$tmp_membermanagement_columns = [
    'date_of_birth' => [
        'exclude' => true,
        'label' => 'Date of Birth',
        'config' => [
            'type' => 'input',
            'renderType' => 'inputDateTime',
            'eval' => 'date',
            'default' => 0,
            'size' => 12,
            'maxitems' => 1,
            'placeholder' => 'YYYY-MM-DD',
        ],
    ],
    'gender' => [
        'exclude' => true,
        'label' => 'Gender',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'Other', 'value' => 0],
                ['label' => 'Male', 'value' => 1],
                ['label' => 'Female', 'value' => 2],
            ],
            'default' => 0,
            'size' => 1,
            'maxitems' => 1,
        ],
    ],
    'iban' => [
        'exclude' => true,
        'label' => 'IBAN',
        'config' => [
            'type' => 'input',
            'size' => 36,
            'eval' => 'trim',
        ],
    ],
    'bic' => [
        'exclude' => true,
        'label' => 'BIC',
        'config' => [
            'type' => 'input',
            'size' => 11,
            'eval' => 'trim',
        ],
    ],
    'privacy_accepted_at' => [
        'exclude' => true,
        'label' => 'Privacy terms accepted on',
        'config' => [
            'type' => 'datetime',
            'default' => 0,
        ],
    ],
    'member_since' => [
        'exclude' => true,
        'label' => 'Member since',
        'config' => [
            'type' => 'datetime',
            'default' => 0,
        ],
    ],
    'member_until' => [
        'exclude' => true,
        'label' => 'Member until',
        'config' => [
            'type' => 'datetime',
            'default' => 0,
        ],
    ],
    'membership' => [
        'exclude' => true,
        'label' => 'Membership',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'foreign_table' => 'tx_membermanagement_membership',
            'minitems' => 0,
            'maxitems' => 1,
            'items' => [
                ['label' => 'No Membership', 'value' => 0],
            ],
        ],
    ],
    'membership_status' => [
        'exclude' => true,
        'label' => 'Membership Status',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'minitems' => 1,
            'maxitems' => 1,
            'items' => [
                ['label' => 'Pending', 'value' => 0],
                ['label' => 'Active', 'value' => 1],
                ['label' => 'Inactive', 'value' => 2],
            ],
            'default' => 0,
        ],
    ],
    'payments' => [
        'exclude' => true,
        'label' => 'Payments',
        'config' => [
            'type' => 'inline',
            'foreign_table' => 'tx_membermanagement_payment',
            'foreign_field' => 'user',
            'maxitems' => 9999,
            'appearance' => [
                'collapseAll' => true,
                'levelLinksPosition' => 'bottom',
            ],
        ],
    ],
    'notes' => [
        'exclude' => true,
        'label' => 'Notes',
        'config' => [
            'type' => 'text',
            'cols' => 30,
            'rows' => 5,
            'eval' => 'trim',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $tmp_membermanagement_columns);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    'date_of_birth, gender, iban, bic, privacy_accepted_at, member_since, member_until, membership, membership_status, payments, notes',
);
