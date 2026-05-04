<?php

declare(strict_types=1);

return [
    'env'   => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'   => rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/'),

    'jwt' => [
        'secret'             => $_ENV['JWT_SECRET'] ?? '',
        'ttl_hours'          => (int) ($_ENV['JWT_TTL_HOURS']           ?? 720),   // 30 días default
        'remember_ttl_hours' => (int) ($_ENV['JWT_REMEMBER_TTL_HOURS']  ?? 2160),  // 90 días si "remember" extra largo
        'algo'               => 'HS256',
        'issuer'             => 'giftcards-app',
    ],

    'upload' => [
        'max_mb' => (int) ($_ENV['UPLOAD_MAX_MB'] ?? 2),
        'path'   => $_ENV['UPLOAD_PATH'] ?? 'public/uploads',
    ],
];
