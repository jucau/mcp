<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use DomainException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Resource;

#[MimeType('application/json')]
abstract class LibraryResource extends Resource
{
    final public function handle(Request $request): Response
    {
        try {
            return Response::text(json_encode($this->read($request), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (DomainException $domainException) {
            // The SDK turns this into a JSON-RPC error that keeps its message; a thrown exception would only keep
            // it with APP_DEBUG on.
            return Response::error($domainException->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function read(Request $request): array;
}
