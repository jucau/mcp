<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api;

use App\Library\Infrastructure\Auth\ProtectedResource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AnnounceResourceMetadata
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
            $response->headers->set('WWW-Authenticate', sprintf(
                'Bearer resource_metadata="%s"',
                url('.well-known/oauth-protected-resource/' . ProtectedResource::Api->value),
            ));
        }

        return $response;
    }
}
