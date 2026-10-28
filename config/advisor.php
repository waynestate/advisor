<?php

// Every value comes from an environment variable; the names are listed in the README.
// ADVISOR_PROJECT_ROOT defaults to the application's base path.
return [
    'env' => [
        'ADVISOR_ENABLED' => env('ADVISOR_ENABLED'),
        'ADVISOR_ENV' => env('ADVISOR_ENV'),
        'ADVISOR_PROJECT_ROOT' => env('ADVISOR_PROJECT_ROOT'),
        'ADVISOR_READ_ONLY' => env('ADVISOR_READ_ONLY'),
        'ADVISOR_GROUPS' => env('ADVISOR_GROUPS'),
        'ADVISOR_PROCTOR_TIMEOUT' => env('ADVISOR_PROCTOR_TIMEOUT'),
        'ADVISOR_MAX_CHARS' => env('ADVISOR_MAX_CHARS'),
        'ADVISOR_AUDIT_LOG' => env('ADVISOR_AUDIT_LOG'),
        'ADVISOR_LOG_LEVEL' => env('ADVISOR_LOG_LEVEL'),
        'ADVISOR_MODULES_FILE' => env('ADVISOR_MODULES_FILE'),
    ],
];
