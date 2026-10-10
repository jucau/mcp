<?php

declare(strict_types=1);

use App\Library\Infrastructure\Api\OpenApi\RefusalToProblem;
use App\Library\Infrastructure\Api\OpenApi\ValidationToProblem;

return [
    'api_path' => 'api',
    'export_path' => 'docs/api/openapi.json',
    'extensions' => [ValidationToProblem::class, RefusalToProblem::class],
    'security_strategy' => null,
];
