<?php

declare(strict_types=1);

namespace TYPO3Incubator\MemberManagement\Service;

use DateTime;
use DateTimeImmutable;
use Digitick\Sepa\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use Digitick\Sepa\PaymentInformation;
use TYPO3Incubator\MemberManagement\Domain\Model\Member;
use TYPO3Incubator\MemberManagement\Domain\Repository\MemberRepository;
use TYPO3Incubator\MemberManagement\Domain\Repository\PaymentRepository;

class PaymentService
{
    private ?ServerRequestInterface $request = null;

    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly PaymentRepository $paymentRepository,
    ) {
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
        $paymentDueMonth = (int)$siteSettings->get('memberManagement.organization.paymentInformation.paymentDueMonth');
        $organizationIban = $siteSettings->get('memberManagement.organization.paymentInformation.iban');
        $organizationBic = $siteSettings->get('memberManagement.organization.paymentInformation.bic');
        $organizationSepaCreditorId = (int)$siteSettings->get('memberManagement.organization.paymentInformation.sepaCreditorId');
        $dueDate = $this->getDueDate($paymentDueMonth);
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

    private function getDueDate(int $dueMonth): DateTime
    {
        $now = new DateTimeImmutable();
        $currentMonth = (int)$now->format('n');
        $currentYear = (int)$now->format('Y');

        $year = ($currentMonth >= $dueMonth) ? $currentYear + 1 : $currentYear;

        $immutableDate = (new DateTimeImmutable())->setDate($year, $dueMonth, 1);

        return DateTime::createFromImmutable($immutableDate);
    }

    /**
     * @return array<Member>
     * @throws Exception
     */
    private function getMembersWithOpenPayments(int $membersFolderPid, int $paymentsFolderPid, DateTime $dueDate): array
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
    }
}
