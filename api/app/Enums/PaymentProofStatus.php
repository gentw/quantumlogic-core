<?php

namespace App\Enums;

enum PaymentProofStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
