<?php

namespace App\Enums;

enum RegistrationType: string
{
    case Employee = 'EMPLOYEE';
    case External = 'EXTERNAL';

    public function label(): string
    {
        return $this === self::Employee ? 'CSPC Employee' : 'External Borrower';
    }
}
