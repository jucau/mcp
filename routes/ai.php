<?php

declare(strict_types=1);

use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\RejectForeignOrigins;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('library', LibraryServer::class);

Mcp::oauthRoutes();
Mcp::web('/mcp', LibraryServer::class)->middleware([RejectForeignOrigins::class, 'auth:api']);
