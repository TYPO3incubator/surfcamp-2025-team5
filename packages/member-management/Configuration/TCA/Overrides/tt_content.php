<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function () {
    $cType = ExtensionUtility::registerPlugin(
        'MemberManagement',
        'CreateMembership',
        'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.create_membership.title',
        'status-user-frontend',
        'plugins',
        'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.create_membership.description',
    );

    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        'bodytext',
        $cType,
        'after:subheader_class',
    );

    $GLOBALS['TCA']['tt_content']['types'][$cType]['columnsOverrides'] = [
        'bodytext' => [
            'label' => 'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:tt_content.bodytext.membermanagement_createmember',
            'config' => [
                'enableRichtext' => true,
                'required' => true,
            ],
        ],
    ];
})();
