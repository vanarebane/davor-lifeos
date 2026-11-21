<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxies whose X-Forwarded-For, -Host, -Port, -Proto and -Prefix
    | headers are trusted. Laravel's TrustProxies middleware reads this value.
    |
    | A comma-separated list of IPs / CIDR ranges, e.g. the private Docker
    | networks of the l0 app manager, which serves the app under /<path>/
    | and sends that stripped prefix as X-Forwarded-Prefix. "*" trusts any
    | caller, which also lets clients choose their IP via X-Forwarded-For.
    | Leave it unset to trust none (Laravel Cloud then trusts its own).
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
