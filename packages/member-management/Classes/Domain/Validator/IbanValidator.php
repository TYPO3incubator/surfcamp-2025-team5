<?php

namespace TYPO3Incubator\MemberManagement\Domain\Validator;

use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

class IbanValidator extends AbstractValidator
{
    protected $supportedOptions = [
        'propertyThatNeedsToBeSet' => [null, 'The property that needs to be set for the IBAN to be valid', 'string'],
    ];
    private mixed $sepaDebtorMandateSignDate = null;

    protected function isValid(mixed $value): void
    {
        if (!$this->sepaDebtorMandateSignDate) {
            $this->addError(
                'The SEPA debtor mandate sign date is not set.',
                1471702628,
            );
            return;
        }

        $iban = strtoupper($value);
        if (!verify_iban($iban)) {
            $this->addError(
                'The entered IBAN is not valid.',
                1471702628,
            );
        }
    }

    public function getPropertyThatNeedsToBeSet(): string
    {
        return $this->options['propertyThatNeedsToBeSet'];
    }

    public function setSepaDebtorMandateSignDate(mixed $sepaDebtorMandateSignDate): void
    {
        $this->sepaDebtorMandateSignDate = $sepaDebtorMandateSignDate;
    }
}
