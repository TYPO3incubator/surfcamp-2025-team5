<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3Incubator\MemberManagement\Controller\MembershipController;

defined('TYPO3') or die();

ExtensionUtility::configurePlugin(
    'MemberManagement',
    'CreateMembership',
    [
        MembershipController::class => 'create, save',
    ],
    [
        MembershipController::class => 'create, save',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);
