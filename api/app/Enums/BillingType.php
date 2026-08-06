<?php

namespace App\Enums;

enum BillingType: string
{
    case OneOff = 'one_off';
    case Recurring = 'recurring';
    case Milestone = 'milestone';
}
