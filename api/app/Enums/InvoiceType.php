<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Deposit = 'deposit';
    case Milestone = 'milestone';
    case Balance = 'balance';
    case OneOff = 'one_off';
    case Recurring = 'recurring';
    case CreditNote = 'credit_note';
}
