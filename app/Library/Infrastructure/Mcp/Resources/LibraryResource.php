<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Domain\Refusal;
use Illuminate\Validation\ValidationException;
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
        } catch (Refusal $refusal) {
            // A ValidationException is the only exception the SDK turns into -32602 (invalid params, a 400 over
            // HTTP) instead of -32603 (internal error, a 500), and it keeps its message even with APP_DEBUG off.
            throw ValidationException::withMessages(['uri' => $refusal->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function read(Request $request): array;
}
