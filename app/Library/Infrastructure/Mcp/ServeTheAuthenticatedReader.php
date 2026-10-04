<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp;

use App\Library\Infrastructure\Reader\AuthenticatedReader;
use App\Library\Infrastructure\Reader\CurrentReader;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServeTheAuthenticatedReader
{
    public function __construct(private Container $container)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->container->bind(CurrentReader::class, AuthenticatedReader::class);

        return $next($request);
    }
}
