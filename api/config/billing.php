<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoicing
    |--------------------------------------------------------------------------
    |
    | Invoice numbers are gapless per Austrian law (fortlaufende
    | Rechnungsnummer): QL-2026-0001, allocated by InvoiceNumberService under
    | a row lock at issue time — never at draft creation.
    |
    */

    'invoice_number_prefix' => env('BILLING_INVOICE_PREFIX', 'QL'),

    // Default payment terms in days (NET 14). Configurable per invoice.
    'payment_terms_days' => (int) env('BILLING_PAYMENT_TERMS_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | VAT
    |--------------------------------------------------------------------------
    |
    | The seller is established in Austria. The standard rate applies
    | domestically; EU B2B with a valid UID is reverse-charged at 0%; non-EU
    | customers are zero-rated as service exports. Resolution happens in
    | TaxService and the resolved rate is stored on the invoice line —
    | historical invoices are never recomputed from this file.
    |
    */

    'seller_country' => env('BILLING_SELLER_COUNTRY', 'AT'),

    'vat_rate' => (float) env('BILLING_VAT_RATE', 20.0),

    // When false, a customer UID is trusted as admin-entered / format-checked
    // only. Switch on to validate against VIES at entry time.
    'vies_live_check' => (bool) env('BILLING_VIES_LIVE_CHECK', false),

];
