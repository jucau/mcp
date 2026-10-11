<?php

declare(strict_types=1);

use App\Library\Domain\Refusal;
use App\Library\Infrastructure\Api\AnnounceResourceMetadata;
use App\Library\Infrastructure\Api\ProblemDetails;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel sorts authentication ahead of any middleware missing from its priority list: the 401 would be
        // answered before the header that says where to get a token could be added.
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: AnnounceResourceMetadata::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'mcp') || $request->expectsJson(),
        );
        $exceptions->render(
            fn (Refusal $refusal, Request $request) => $request->is('api/*') ? ProblemDetails::forRefusal($refusal) : null,
        );
        $exceptions->render(
            fn (ValidationException $invalid, Request $request) => $request->is('api/*') ? ProblemDetails::forInvalidRequest($invalid) : null,
        );
    })->create();
