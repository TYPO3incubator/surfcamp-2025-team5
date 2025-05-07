<?php

declare(strict_types=1);

use TYPO3Incubator\MemberManagement\Domain\Model\Member;

return [
    Member::class => [
        'tableName' => 'fe_users',
        'recordType' => 'tx_member_management_member',
        'properties' => [
            'disabled' => [
                'fieldName' => 'disable',
            ],
        ],
    ],
];
