<?php

namespace TYPO3Incubator\MemberManagement\Domain\Model;

enum Gender: int
{
    case Other  = 0;
    case Male   = 1;
    case Female = 2;

    public function label(): string
    {
        return match($this) {
            self::Other  => 'Other',
            self::Male   => 'Male',
            self::Female => 'Female',
        };
    }
}
