<?php

namespace TYPO3Incubator\MemberManagement\Domain\Validator;

use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

class IbanValidator extends AbstractValidator
{

    protected function isValid(mixed $value): void
    {
        $iban = strtoupper($value);
        if (!verify_iban($iban)) {
            $this->addError(
                'The entered IBAN is not valid.',
                1471702628,
            );
        }
    }
}
