<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Stripe = 'stripe';
    case PayPal = 'paypal';
    case BankTransfer = 'bank_transfer';
    case Manual = 'manual';
}
