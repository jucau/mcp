<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Tools;

use DomainException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\StringType;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class LibraryTool extends Tool
{
    final public function handle(Request $request): Response|ResponseFactory
    {
        try {
            return $this->respond($request);
        } catch (DomainException $domainException) {
            // A business refusal is for the model to read and act on. Left to the SDK, its message would only
            // reach the model with APP_DEBUG on, and be replaced by a generic internal error in production.
            return Response::error($domainException->getMessage());
        }
    }

    abstract protected function respond(Request $request): Response|ResponseFactory;

    protected function bookId(JsonSchema $schema): StringType
    {
        return $schema->string()->format('uuid')->required()
            ->description('The id of the book, as returned by search_books.');
    }

    protected function day(JsonSchema $schema): StringType
    {
        return $schema->string()->format('date')
            ->description('The day it happened (YYYY-MM-DD), only if the user said. Leave it out for today: never guess a date.');
    }
}
