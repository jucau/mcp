<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireTokenFor
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $account = $request->user('api');
        $token = $account instanceof User ? $account->token() : null;

        if (! $token instanceof AccessToken || $token->resource !== ProtectedResource::from($resource)->uri()) {
            throw new AuthenticationException('This token was not issued for this resource.', ['api']);
        }

        return $next($request);
    }
}
