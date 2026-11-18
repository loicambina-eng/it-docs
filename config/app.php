<?php
// Role du fichier: Parametres generaux de IT-Docs.
return [
    'name' => env('APP_NAME', 'IT-Docs'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'Africa/Libreville'),
    'locale' => env('APP_LOCALE', 'fr'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'fr'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'fr_FR'),
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'company_name' => env('APP_COMPANY_NAME', 'IT-CORP'),
    'maintenance' => ['driver' => env('APP_MAINTENANCE_DRIVER', 'file'), 'store' => env('APP_MAINTENANCE_STORE', 'database')],
];
