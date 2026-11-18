<?php
// Role du fichier: Configuration du cache Laravel.
return [
    'default' => env('CACHE_STORE', 'file'),
    'stores' => [
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')],
        'array' => ['driver' => 'array', 'serialize' => false],
    ],
    'prefix' => env('CACHE_PREFIX', 'it_docs_cache'),
];
