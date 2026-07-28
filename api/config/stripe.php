<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    |
    | Cards + SCA/3DS through the Payment Element. The webhook is the source
    | of truth for payment application — the browser return only navigates.
    |
    */

    'key' => env('STRIPE_KEY'),

    'secret' => env('STRIPE_SECRET'),

    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

];
