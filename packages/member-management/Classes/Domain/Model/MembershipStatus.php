<?php

namespace TYPO3Incubator\MemberManagement\Domain\Model;

enum MembershipStatus: int
{
    case Pending  = 0;
    case Active   = 1;
    case Inactive = 2;

    public function label(): string
    {
        return match($this) {
            self::Pending  => 'Pending',
            self::Active   => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
