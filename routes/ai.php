<?php

declare(strict_types=1);

use App\Library\Infrastructure\Mcp\LibraryServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('library', LibraryServer::class);
