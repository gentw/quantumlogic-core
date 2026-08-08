<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company identity (invoice legal content, bank transfer details)
    |--------------------------------------------------------------------------
    |
    | §11 UStG mandatory invoice content and the SEPA beneficiary. Never
    | hardcode any of this in a Blade template or controller — invoices and
    | EPC QR payloads read from here. Populate the real values in .env
    | before go-live (open item in the feature spec).
    |
    */

    'legal_name' => env('COMPANY_LEGAL_NAME', 'QuantumLogic'),

    'address' => env('COMPANY_ADDRESS', ''),

    // UID-Nummer (ATU...)
    'uid' => env('COMPANY_UID', ''),

    // Firmenbuchnummer + registration court
    'register_number' => env('COMPANY_REGISTER_NO', ''),
    'register_court' => env('COMPANY_REGISTER_COURT', ''),

    'email' => env('COMPANY_EMAIL', ''),
    'phone' => env('COMPANY_PHONE', ''),

    // SEPA beneficiary
    'iban' => env('COMPANY_IBAN', ''),
    'bic' => env('COMPANY_BIC', ''),
    'bank_name' => env('COMPANY_BANK_NAME', ''),

    /*
    | The EPC069-12 "Giro-Code". Switch it off while the beneficiary details
    | above are still blank — a QR encoding an empty IBAN scans successfully
    | and hands the payer a broken transfer, which is worse than no QR at all.
    | Every surface that renders one hides it when this is false.
    */
    'epc_qr' => (bool) env('BILLING_EPC_QR', true),

];
