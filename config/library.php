<?php

declare(strict_types=1);

return [
    'reader' => env('LIBRARY_READER'),
    'reader_timezone' => env('LIBRARY_READER_TIMEZONE', 'UTC'),
    'front_url' => rtrim((string) env('FRONT_URL', 'http://localhost:5173'), '/'),
    'front_client_id' => env('LIBRARY_FRONT_CLIENT_ID', '019a0000-0000-7000-8000-00000000f40e'),
    'catalog' => env('LIBRARY_CATALOG', 'open-library'),
    'catalog_url' => rtrim((string) env('LIBRARY_CATALOG_URL', 'https://openlibrary.org'), '/'),
    'mcp_allowed_origins' => [rtrim((string) env('APP_URL', 'http://localhost'), '/')],
];
