<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "member_management".
 *
 * Copyright (C) 2025 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace TYPO3Incubator\MemberManagement\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3Incubator\MemberManagement\Domain\Model\Member;

/**
 * MembershipController
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class MembershipController extends ActionController
{
    public function __construct(
        private readonly PersistenceManagerInterface $persistenceManager,
    ) {}

    protected function createAction(): ResponseInterface
    {
        $this->view->assign('member', $member ?? new Member());

        return $this->htmlResponse();
    }

    protected function initializeSaveAction(): void
    {
        $this->arguments->getArgument('member')
            ->getPropertyMappingConfiguration()
            ->forProperty('dateOfBirth')
            ->setTypeConverterOption(
                DateTimeConverter::class,
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                'Y-m-d',
            )
        ;
    }

    protected function saveAction(Member $member): ResponseInterface
    {
        $this->persistenceManager->add($member);
        $this->persistenceManager->persistAll();

        // @todo create full membership
        // @todo send double opt-in mail

        return $this->htmlResponse();
    }

    protected function confirmationAction(string $hash): ResponseInterface
    {
        // @todo validate hash

        return $this->htmlResponse();
    }
}
