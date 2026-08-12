<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | Every locale the application can render. Anything resolved outside this
    | list falls back to `app.fallback_locale`. Adding one here is not enough —
    | it also needs a `lang/<code>/` directory and a matching JSON file under
    | `web/src/plugins/i18n/locales/`.
    |
    */

    'supported' => ['en', 'de', 'sq'],

    /*
    |--------------------------------------------------------------------------
    | Country → locale
    |--------------------------------------------------------------------------
    |
    | Which language a visitor from a given country sees before they have said
    | otherwise. Austria is the home market; German also covers DE/CH/LI.
    | Albanian covers Albania, Kosovo and North Macedonia. Everywhere else gets
    | English, so this map only lists the exceptions to that.
    |
    */

    'country_map' => [
        'AT' => 'de',
        'DE' => 'de',
        'CH' => 'de',
        'LI' => 'de',

        'AL' => 'sq',
        'XK' => 'sq',
        'MK' => 'sq',
    ],

    /*
    |--------------------------------------------------------------------------
    | GeoIP database
    |--------------------------------------------------------------------------
    |
    | Optional MaxMind GeoLite2-Country database, used only when the request did
    | not arrive with a CF-IPCountry header. Neither the geoip2/geoip2 package
    | nor the .mmdb file ships with the repo — the database needs a MaxMind
    | licence key and its own update schedule. LocaleService checks for both at
    | runtime and simply skips this step when either is absent, so the app works
    | without it; behind Cloudflare the header alone is enough.
    |
    | To enable: composer require geoip2/geoip2, then place the .mmdb at the
    | path below (or point GEOIP_DATABASE_PATH somewhere else).
    |
    */

    'geoip_database' => env('GEOIP_DATABASE_PATH', storage_path('app/geoip/GeoLite2-Country.mmdb')),

];
