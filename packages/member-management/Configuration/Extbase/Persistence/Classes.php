<?php

declare(strict_types=1);

use TYPO3Incubator\MemberManagement\Domain\Model\Member;

return [
    Member::class => [
        'tableName' => 'fe_users',
        'recordType' => Member::class,
        'properties' => [
            // @todo Add additional properties
            // '<propertyName>' => [
            //      'fieldName' => '<fieldName>',
            // ],
        ],
    ],
];
