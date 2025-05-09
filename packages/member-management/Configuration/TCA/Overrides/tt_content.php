<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function () {
    $createMembershipType = ExtensionUtility::registerPlugin(
        'MemberManagement',
        'CreateMembership',
        'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.create_membership.title',
        'status-user-frontend',
        'plugins',
        'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.create_membership.description',
    );

    ExtensionUtility::registerPlugin(
        'MemberManagement',
        'ConfirmMembership',
        'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.confirm_membership.title',
        'status-user-frontend',
        'plugins',
        'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.confirm_membership.description',
    );

    ExtensionUtility::registerPlugin(
        'MemberManagement',
        'MembershipSettings',
        'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.membership_settings.title',
        'status-user-frontend',
        'plugins',
        'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.membership_settings.description',
    );

    ExtensionManagementUtility::addTCAcolumns('tt_content', [
        'tx_membermanagement_confirmation_pid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:tt_content.tx_membermanagement_confirmation_pid',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'foreign_table' => 'pages',
                'maxitems' => 1,
                'minitems' => 1,
                'size' => 1,
            ],
        ],
    ]);

    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        'bodytext,tx_membermanagement_confirmation_pid',
        $createMembershipType,
        'after:subheader_class',
    );

    $GLOBALS['TCA']['tt_content']['types'][$createMembershipType]['columnsOverrides'] = [
        'bodytext' => [
            'label' => 'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:tt_content.bodytext.membermanagement_createmember',
            'config' => [
                'enableRichtext' => true,
                'required' => true,
            ],
        ],
    ];
})();
