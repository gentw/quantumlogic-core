<?php

namespace App\Http\Requests;

/**
 * A payment proof uploaded against a public pay-link token instead of a
 * session. Authorisation is the token itself, which the controller resolves —
 * there is no user to compare against here. The file rules are inherited
 * unchanged, so both routes accept exactly the same uploads.
 */
class PublicPaymentProofRequest extends PaymentProofRequest
{
    public function authorize(): bool
    {
        return true; // the 64-char token is the credential; route is throttled
    }
}
