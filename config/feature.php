<?php

return [
    'import-suggestions' => [
        'enabled' => env('IMPORT_SUGGESTIONS_ENABLED', true),
        'chunk_size' => 200,
        'timeout' => 120,
    ],
];
