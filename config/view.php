<?php
// Role du fichier: Configuration des vues Blade et de leur cache.
// Laravel compile les vues Blade dans ce dossier avant de les afficher.
return [
    'paths' => [resource_path('views')],
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views')) ?: storage_path('framework/views')
    ),
];
