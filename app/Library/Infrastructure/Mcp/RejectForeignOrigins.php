<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

/**
 * Closes DNS rebinding: a browser always sends Origin, and a script cannot forge it. Non-browser clients
 * (Claude Code) send none, so a missing Origin passes; authenticating them is another layer's job.
 */
final readonly class RejectForeignOrigins
{
    public function __construct(private Repository $config)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin === null || $this->isAllowed($origin)) {
            return $next($request);
        }

        return new JsonResponse([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32600, 'message' => "The origin [{$origin}] is not allowed."],
        ], Response::HTTP_FORBIDDEN);
    }

    private function isAllowed(string $origin): bool
    {
        $allowed = $this->config->get('library.mcp_allowed_origins');
        Assert::isList($allowed);

        return in_array($origin, $allowed, true);
    }
}
