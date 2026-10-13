<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
