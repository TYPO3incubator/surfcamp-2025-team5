<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

ExtensionUtility::registerPlugin(
    'MemberManagement',
    'CreateMembership',
    'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.create_membership.title',
    'status-user-frontend',
    'plugins',
    'LLL:EXT:member_management/Resources/Private/Language/locallang_db.xlf:plugins.create_membership.description',
);
