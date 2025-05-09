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

namespace TYPO3Incubator\MemberManagement\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3Incubator\MemberManagement\Domain\Model\Member;
use TYPO3Incubator\MemberManagement\Domain\Model\MembershipStatus;
use TYPO3Incubator\MemberManagement\Domain\Repository\MemberRepository;
use TYPO3Incubator\MemberManagement\Exception\MemberIsAlreadyConfirmed;
use TYPO3Incubator\MemberManagement\Exception\MemberIsAlreadyCreated;
use TYPO3Incubator\MemberManagement\Exception\MemberIsNoLongerInAnActiveMembership;
use TYPO3Incubator\MemberManagement\Exception\MemberIsNotProperlyPersisted;

/**
 * MembershipService
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class MembershipService
{
    private ?ServerRequestInterface $request = null;

    public function __construct(
        private readonly EmailService $emailService,
        private readonly HashService $hashService,
        private readonly LoggerInterface $logger,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly MemberRepository $memberRepository,
    ) {
    }

    /**
     * @throws MemberIsAlreadyConfirmed
     * @throws MemberIsAlreadyCreated
     * @throws MemberIsNoLongerInAnActiveMembership
     * @throws MemberIsNotProperlyPersisted
     */
    public function create(Member $member, int $confirmationPid = 0): bool
    {
        $uid = $member->getUid();
        $email = $member->getEmail();

        // Throw on invalid member(ship) state
        if ($uid === null || $email === '') {
            throw new MemberIsNotProperlyPersisted();
        }
        if ($member->getCreateHash() !== '') {
            throw new MemberIsAlreadyCreated($member);
        }
        if ($member->getMembershipStatus() !== MembershipStatus::Unconfirmed) {
            throw new MemberIsAlreadyConfirmed($member);
        }
        if ($member->getMemberUntil()?->getTimestamp() > time()) {
            throw new MemberIsNoLongerInAnActiveMembership($member);
        }

        // Create and attach hash
        $hash = $this->hashService->hmac((string) $uid, $email);
        $member->setCreateHash($hash);

        // Persist updated member
        $this->persistenceManager->update($member);
        $this->persistenceManager->persistAll();

        // Send mail to new member
        $email = $this->emailService->createEmail(
            'CreateMembership',
            'LLL:EXT:member_management/Resources/Private/Language/locallang.xlf:email.createMembership.subject',
            $member,
        );
        $email->assignMultiple([
            'confirmationPid' => $confirmationPid,
            'hash' => $hash,
        ]);

        try {
            return $this->emailService->sendEmail($email);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error(
                'Error while sending membership double-opt-in mail: {message}',
                ['message' => $exception->getMessage()],
            );

            return false;
        }
    }

    /**
     * @throws MemberIsAlreadyConfirmed
     * @throws MemberIsNoLongerInAnActiveMembership
     */
    public function confirm(Member $member): bool
    {
        $managerEmailAddress = $this->getSiteSettings()?->get('memberManagement.organization.emailOfPersonInCharge');

        if ($member->getMembershipStatus() !== MembershipStatus::Unconfirmed) {
            throw new MemberIsAlreadyConfirmed($member);
        }
        if ($member->getMemberUntil()?->getTimestamp() > time()) {
            throw new MemberIsNoLongerInAnActiveMembership($member);
        }

        // Confirm membership
        $member->setCreateHash('');
        $member->setMembershipStatus(MembershipStatus::Pending);

        // Update member in database
        $this->persistenceManager->update($member);
        $this->persistenceManager->persistAll();

        // Early return if no manager email address is configured
        if (!is_string($managerEmailAddress) || trim($managerEmailAddress) === '') {
            $this->logger->warning(
                'No email address configured for person in charge of member management, manager email skipped.',
            );

            return true;
        }

        $email = $this->emailService->createEmail(
            'NewMembership',
            'LLL:EXT:member_management/Resources/Private/Language/locallang.xlf:email.newMembership.subject',
            $member,
            new Address($managerEmailAddress),
        );

        $email->assign('beMemberPid', $this->getSiteSettings()?->get('felogin.pid'));

        try {
            return $this->emailService->sendEmail($email);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error(
                'Error while sending membership confirmation mail to manager: {message}',
                ['message' => $exception->getMessage()],
            );

            return false;
        }
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
        $this->emailService->setRequest($request);
    }

    private function getSiteSettings(): ?SiteSettings
    {
        $site = $this->request?->getAttribute('site');

        if (!($site instanceof Site)) {
            return null;
        }

        return $site->getSettings();
    }

    public function setMembersActive(array $memberUids) {
        foreach ($memberUids as $memberUid) {
            $member = $this->memberRepository->findByUid($memberUid);
            if ($member->getMembershipStatus() === MembershipStatus::Active) {
                continue;
            }
            $member->setMembershipStatus(MembershipStatus::Active);
            $member->setDisabled(false);
            $this->memberRepository->update($member);

            $email = $this->emailService->createEmail(
                'MembershipActivated',
                'LLL:EXT:member_management/Resources/Private/Language/locallang.xlf:email.membershipActivated.subject',
                $member,
            );

            $email->assign('sitesets', $this->getSiteSettings()->getAll());

            try {
                $this->emailService->sendEmail($email);
            } catch (TransportExceptionInterface $exception) {
                $this->logger->error(
                    'Error while sending new membership confirmatiomn mail: {message}',
                    ['message' => $exception->getMessage()],
                );
            }
        }
    }

    public function setMembersInactive(array $memberUids) {
        foreach ($memberUids as $memberUid) {
            $member = $this->memberRepository->findByUid($memberUid);
            if ($member->getMembershipStatus() === MembershipStatus::Inactive) {
                continue;
            }
            $member->setMembershipStatus(MembershipStatus::Inactive);
            $member->setDisabled(true);
            $this->memberRepository->update($member);
        }
        $this->persistenceManager->persistAll();
    }

    private function getBackendUserAuthentication(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if ($backendUser instanceof BackendUserAuthentication) {
            return $backendUser;
        }

        return null;
    }
}
