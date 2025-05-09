<?php

declare(strict_types=1);

namespace TYPO3Incubator\MemberManagement\Service;

use Digitick\Sepa\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use Digitick\Sepa\PaymentInformation;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3Incubator\MemberManagement\Domain\Model\Member;
use TYPO3Incubator\MemberManagement\Domain\Model\MembershipStatus;
use TYPO3Incubator\MemberManagement\Domain\Model\Payment;
use TYPO3Incubator\MemberManagement\Domain\Model\PaymentState;
use TYPO3Incubator\MemberManagement\Domain\Repository\MemberRepository;
use TYPO3Incubator\MemberManagement\Domain\Repository\PaymentRepository;
use TYPO3Incubator\MemberManagement\Payment\PaymentManagementAction;
use TYPO3Incubator\MemberManagement\Payment\PaymentManagementResult;

final class PaymentService
{
    private ?ServerRequestInterface $request = null;

    public function __construct(
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
        private readonly MemberRepository $memberRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly SiteFinder $siteFinder,
    ) {
    }

    public function processMemberPayments(Member $member): PaymentManagementResult
    {
        // Don't create payments for non-active members
        if ($member->getMembershipStatus() !== MembershipStatus::Active) {
            return new PaymentManagementResult(PaymentManagementAction::Nothing, $member);
        }

        /** @var Payment|null $lastPayment */
        $lastPayment = $member->getLastPayment();

        // Create first payment for new active member
        if ($lastPayment === null) {
            $payment = $this->createPayment($member, true);

            return new PaymentManagementResult(PaymentManagementAction::NewPaymentCreated, $member, $payment);
        }

        if ($lastPayment->getState() === PaymentState::Pending) {
            // Only one "reminder" mail is sent, subsequent actions must be taken manually
            if ($lastPayment->getReminderMailSentAt() !== null) {
                return new PaymentManagementResult(PaymentManagementAction::ManualActionRequired, $member, $lastPayment);
            }

            // @todo convert to site setting
            $reminderPeriod = 'P14D'; // 14 days
            $reminderInterval = new \DateInterval($reminderPeriod);
            $dueBy = $lastPayment->getDueBy();

            // Send "reminder" mail if not done yet
            if ($member->getIban() === '' && $dueBy !== null && (clone $dueBy)->sub($reminderInterval)->getTimestamp() < time()) {
                $email = $this->emailService->createEmail(
                    'PaymentReminder',
                    'LLL:EXT:member_management/Resources/Private/Language/locallang.xlf:email.paymentReminder.subject',
                    $member,
                );
                $email->assign('payment', $lastPayment);

                try {
                    $this->emailService->sendEmail($email);
                } catch (TransportExceptionInterface $exception) {
                    $this->logger->error(
                        'Error while sending payment reminder mail: {message}',
                        ['message' => $exception->getMessage()],
                    );

                    return new PaymentManagementResult(PaymentManagementAction::ReminderMailCouldNotBeSent, $member, $lastPayment);
                }

                $lastPayment->setReminderMailSentAt(new \DateTime());

                $this->persistenceManager->update($lastPayment);
                $this->persistenceManager->persistAll();

                return new PaymentManagementResult(PaymentManagementAction::ReminderMailSent, $member, $lastPayment);
            }

            return new PaymentManagementResult(PaymentManagementAction::Nothing, $member, $lastPayment);
        }

        // Create next payment if applicable
        if ($lastPayment->getState() === PaymentState::Paid) {
            $payment = $this->createPayment($member);

            if ($payment !== null) {
                return new PaymentManagementResult(PaymentManagementAction::NewPaymentCreated, $member, $payment);
            }
        }

        return new PaymentManagementResult(PaymentManagementAction::Nothing, $member, $lastPayment);
    }

    public function createPayment(Member $member, bool $ignoreGracePeriod = false): ?Payment
    {
        $site = $this->siteFinder->getSiteByPageId((int) $member->getPid());
        $dueDate = $this->getDueDate($site);

        if (!$ignoreGracePeriod) {
            // @todo convert to site setting
            $gracePeriod = 'P3M'; // 3 weeks
            $interval = new \DateInterval($gracePeriod);

            // No payment needed if outside of grace period
            if ($dueDate->sub($interval)->getTimestamp() < time()) {
                return null;
            }
        }

        $membership = $member->getMembership();

        // Early return if member has no membership associated
        if ($membership === null) {
            return null;
        }

        $payment = GeneralUtility::makeInstance(Payment::class);
        $payment->setMember($member);
        $payment->setDueBy($dueDate);
        $payment->setAmount($membership->getPrice());
        $payment->setState(PaymentState::Pending);
        $payment->setPid((int) $site->getSettings()->get('memberManagement.storage.paymentsFolderPid'));

        $this->persistenceManager->add($payment);
        $this->persistenceManager->persistAll();

        return $payment;
    }

    public function cancelPendingPayments(Member $member): void
    {
        $hasChanges = false;

        /** @var Payment $payment */
        foreach ($member->getPayments() as $payment) {
            if ($payment->getstate() === PaymentState::Pending) {
                $payment->setState(PaymentState::Cancelled);
                $hasChanges = true;

                $this->persistenceManager->update($payment);
            }
        }

        if ($hasChanges) {
            $this->persistenceManager->persistAll();
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws ContainerExceptionInterface
     */
    public function generateSepaXml(): ?string
    {
        /** @var Site $site */
        $site = $this->request->getAttribute('site');
        $siteSettings = $site->getSettings();

        $organizationName = $siteSettings->get('memberManagement.organization.name');
        $organizationPersonInCharge = $siteSettings->get('memberManagement.organization.personInCharge');
        $organizationIban = $siteSettings->get('memberManagement.organization.paymentInformation.iban');
        $organizationBic = $siteSettings->get('memberManagement.organization.paymentInformation.bic');
        $organizationSepaCreditorId = (int)$siteSettings->get('memberManagement.organization.paymentInformation.sepaCreditorId');
        $dueDate = $this->getDueDate($site);
        $uniqueMessageIdentification = 'member/' . time();

        $membersFolderPid = (int)$siteSettings->get('felogin.pid');
        $paymentsFolderPid = (int)$siteSettings->get('memberManagement.storage.paymentsFolderPid');

        $membersWithOpenPayments = $this->getMembersWithOpenPayments($membersFolderPid, $paymentsFolderPid, $dueDate);

        if (count($membersWithOpenPayments) === 0) {
            $this->displayBackendFlashMessage(
                'Stopped SEPA XML download',
                'There are no members with open payments.',
                ContextualFeedbackSeverity::INFO,
            );

            return null;
        }

        // Set the initial sepa information
        $directDebit = TransferFileFacadeFactory::createDirectDebit(
            uniqueMessageIdentification: $uniqueMessageIdentification,
            initiatingPartyName: $organizationName . ', ' . $organizationPersonInCharge,
            // painFormat/ SEPA PAIN format = Payments Initiation: standard for communication between customer and bank (ISO 20022)
            painFormat: 'pain.008.003.02'
        );

        $paymentName = 'unix-' . time() . '-';

        // If needed change use ::S_FIRST, ::S_RECURRING or ::S_FINAL respectively
        // @TODO: Payment initial at registration, or always only at the set due date month?
        $directDebit->addPaymentInfo($paymentName, array(
            'id' => $paymentName,
            'dueDate' => $dueDate,
            'creditorName' => $organizationName,
            'creditorAccountIBAN' => $organizationIban,
            'creditorAgentBIC' => $organizationBic,
            'seqType' => PaymentInformation::S_RECURRING,
            // 'seqType' => PaymentInformation::S_ONEOFF,
            'creditorId' => $organizationSepaCreditorId,
            // batch booking option, to enable multiple transfers in one batch
            'batchBooking' => true,
        ));

        foreach ($membersWithOpenPayments as $member) {
            if (!$member->getSepaDebtorMandateSignDate()) {
                continue;
            }

            $membershipFeeInCents = $member->getMembership()?->getPrice() * 100;

            // Add a Single Transaction to the named payment
            $directDebit->addTransfer($paymentName, array(
                'amount' => $membershipFeeInCents,
                'debtorIban' => $member->getIban(),
                'debtorBic' => $member->getBic(),
                'debtorName' => $member->getFirstName() . ' ' . $member->getLastName(),
                'debtorMandate' => $member->getSepaDebtorMandate(),
                'debtorMandateSignDate' => $member->getSepaDebtorMandateSignDate()?->format('Y-m-d'),
                'remittanceInformation' => $organizationName . ' membership fee for ' . $dueDate->format('Y-m-d'),
            ));
        }

        // Retrieve the resulting XML
        return $directDebit->asXML();
    }

    private function getDueDate(Site $site): \DateTime
    {
        $dueMonth = (int)$site->getSettings()->get('memberManagement.organization.paymentInformation.paymentDueMonth', 1);
        $now = new \DateTimeImmutable();
        $currentMonth = (int)$now->format('n');
        $currentYear = (int)$now->format('Y');

        $year = ($currentMonth >= $dueMonth) ? $currentYear + 1 : $currentYear;

        return (new \DateTime())
            ->setDate($year, $dueMonth, 1)
            ->setTime(0, 0)
        ;
    }

    /**
     * @return array<Member>
     * @throws Exception
     */
    private function getMembersWithOpenPayments(int $membersFolderPid, int $paymentsFolderPid, \DateTime $dueDate): array
    {
        $members = $this->memberRepository->findActiveInFolder($membersFolderPid);
        if (count($members) === 0) {
            return [];
        }

        $dueDateYearTimestamp = $dueDate->getTimestamp();

        // A member has an open payment when the member has not already paid this year (before the due date)
        $membersWithOpenPayments = $this->paymentRepository->findMembersWithOpenPayments($paymentsFolderPid, $members, $dueDateYearTimestamp);

        return $membersWithOpenPayments;
    }

    private function displayBackendFlashMessage(
        string $title,
        string $message,
        ContextualFeedbackSeverity $contextualFeedbackSeverity
    ): void {
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $contextualFeedbackSeverity,
            true
        );
        $messageQueue->addMessage($flashMessage);
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
        $this->emailService->setRequest($request);
    }

    public function markMembersAsPaid(array $memberUids): void
    {
        foreach ($memberUids as $memberUid) {
            $member = $this->memberRepository->findByUid($memberUid);
            if ($member === null) {
                continue;
            }

            $payment = $member->getLastPayment();
            if ($payment === null) {
                $payment = $this->createPayment($member, true);
                $this->persistenceManager->add($payment);
            }

            $payment->setState(PaymentState::Paid);
            $payment->setPaidAt(new \DateTime());
            $this->persistenceManager->update($payment);
        }
        $this->persistenceManager->persistAll();
    }
}
