<?php

/*
 * This file is part of the Member Management project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3Incubator\MemberManagement\Controller\BackendMemberController;

return [
    'member_management' => [
        'parent' => 'web',
        'position' => ['after' => 'web_list'],
        'access' => 'user',
        'workspaces' => 'live',
        'labels' => 'LLL:EXT:member_management/Resources/Private/Language/locallang_mod_member.xlf',
        'extensionName' => 'MemberManagement',
        'iconIdentifier' => 'tx-member-management-module',
        'controllerActions' => [
            BackendMemberController::class => [
                'index',
                'memberBulkAction'
            ],
        ],
    ],
];
