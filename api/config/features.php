<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Optional feature modules
    |--------------------------------------------------------------------------
    |
    | Modules that ship with the codebase but are not part of the active
    | QuantumLogic Core product. The code stays in place; these flags decide
    | whether its routes are registered at all.
    |
    | security: origin protection — domains, incoming alarms, threat logs.
    |           Dormant. See docs/modules/security/README.md before enabling.
    |
    */

    'security' => (bool) env('FEATURE_SECURITY_MODULE', false),

];
