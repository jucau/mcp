<?php

declare(strict_types=1);

return [
    'paths' => ['api/*', 'oauth/token', '.well-known/*'],
    'allowed_methods' => ['GET', 'POST', 'DELETE'],
    'allowed_origins' => [rtrim((string) env('FRONT_URL', 'http://localhost:5173'), '/')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type'],
    // A browser hides every response header it is not told it may read: without these, the front could neither
    // follow the 401 to the metadata nor find the book it just added.
    'exposed_headers' => ['Location', 'WWW-Authenticate'],
    'max_age' => 600,
    'supports_credentials' => false,
];
