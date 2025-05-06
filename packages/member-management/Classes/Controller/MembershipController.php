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
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3Incubator\MemberManagement\Domain\Model\Member;
use TYPO3Incubator\MemberManagement\Domain\Repository\MemberRepository;
use TYPO3Incubator\MemberManagement\Exception\Exception;
use TYPO3Incubator\MemberManagement\Service\MembershipService;

/**
 * MembershipController
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class MembershipController extends ActionController
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MembershipService $membershipService,
        private readonly PasswordHashFactory $passwordHashFactory,
        private readonly PersistenceManagerInterface $persistenceManager,
    ) {}

    protected function initializeAction(): void
    {
        $this->membershipService->setRequest($this->request);
    }

    protected function initializeCreateAction(): void
    {
        // Allow "member" only as internal argument when forwarding from "save" action
        if ($this->request->hasArgument('member') &&
            !($this->request->getArgument('member') instanceof Member)
        ) {
            $this->request->withArgument('member', null);
        }
    }

    protected function createAction(?Member $member = null): ResponseInterface
    {
        $this->view->assignMultiple([
            'currentDateFormatted' => (new \DateTimeImmutable())->format(\DateTime::W3C),
            'member' => $member ?? new Member(),
            'sitesets' => $this->request->getAttribute('site')->getSettings()->getAll()
        ]);

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
        $member->setPrivacyAcceptedAt(new \DateTime());
        $member->setPassword(
            $this->passwordHashFactory->getDefaultHashInstance('FE')->getHashedPassword($member->getPassword()),
        );
        $member->setPasswordRepeat('');

        $this->persistenceManager->add($member);
        $this->persistenceManager->persistAll();

        try {
            $created = $this->membershipService->create($member);
        } catch (Exception $exception) {
            $created = false;

            // @todo Use better error message, not only exception message
            $this->addFlashMessage($exception->getMessage());
        }

        if ($created) {
            return $this->htmlResponse();
        }

        // Remove already persisted member if membership could not be created
        $this->persistenceManager->remove($member);
        $this->persistenceManager->persistAll();

        // Obfuscate submitted passwords
        $member->setPassword('');



        return (new ForwardResponse('create'))->withArguments([
            'member' => $member
        ]);
    }

    protected function confirmAction(string $hash, string $email): ResponseInterface
    {
        $member = $this->memberRepository->findOneByHash($hash);

        // Show error if no member with associated hash is found
        if ($member === null) {
            return $this->errorResponse('memberNotFound', 404);
        }

        // Show error if email does not match
        if ($member->getEmail() !== $email) {
            return $this->errorResponse('invalidEmailAddress');
        }

        // Confirm membership
        $member->setCreateHash('');

        // Update member in database
        $this->persistenceManager->update($member);
        $this->persistenceManager->persistAll();

        return $this->htmlResponse();
    }

    private function errorResponse(string $reason, int $statusCode = 400): ResponseInterface
    {
        $this->view->assign('error', $reason);

        $response = $this->htmlResponse(
            $this->view->render('Error'),
        );

        return $response->withStatus($statusCode);
    }
}
