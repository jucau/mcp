<?php

declare(strict_types=1);

return [
    'reader' => env('LIBRARY_READER'),
    'reader_timezone' => env('LIBRARY_READER_TIMEZONE', 'UTC'),
    'mcp_allowed_origins' => [rtrim((string) env('APP_URL', 'http://localhost'), '/')],
];
