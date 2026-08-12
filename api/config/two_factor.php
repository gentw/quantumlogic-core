<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Roles that get two-factor switched on by default
    |--------------------------------------------------------------------------
    |
    | Staff hold every client's data, invoices and payments, so their accounts
    | start with the email code required. Clients start without it and can turn
    | it on themselves.
    |
    | This is a default, not a lock: anyone can change their own setting from
    | account settings. It decides what a brand-new account starts with, and it
    | is what the backfill migration used for accounts that already existed.
    |
    | Worth knowing when reading this: before this feature, admins and agents
    | were never sent a code at all — the login controller minted their token
    | directly — so switching them on here is new behaviour for them, not a
    | restoration.
    |
    */

    'default_on_roles' => ['admin', 'agent'],

    /*
    |--------------------------------------------------------------------------
    | Code lifetime
    |--------------------------------------------------------------------------
    |
    | Minutes a login code stays valid. Kept at the 10 minutes the previous
    | inline implementation used.
    |
    */

    'code_ttl_minutes' => (int) env('TWO_FACTOR_CODE_TTL', 10),

];
