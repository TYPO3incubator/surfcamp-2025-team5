<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'tx-member-management-module' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:member_management/Resources/Public/Icons/Extension.svg',
    ],
];
