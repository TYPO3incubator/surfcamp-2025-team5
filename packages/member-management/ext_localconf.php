<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3Incubator\MemberManagement\Controller\MembershipController;

defined('TYPO3') or die();

ExtensionUtility::configurePlugin(
    'MemberManagement',
    'CreateMembership',
    [
        MembershipController::class => 'create, save, confirm',
    ],
    [
        MembershipController::class => 'create, save, confirm',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);

$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][1746439249]
    = 'EXT:member_management/Resources/Private/Templates/Email/';
