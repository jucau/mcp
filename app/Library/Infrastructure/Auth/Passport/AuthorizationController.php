<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth\Passport;

use App\Library\Infrastructure\Auth\ProtectedResource;
use App\Library\Infrastructure\Auth\ResourceIndicator;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\Http\Controllers\AuthorizationController as PassportAuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizationController extends PassportAuthorizationController
{
    public const string RESOURCE = 'oauth.resource';

    public function __construct(
        AuthorizationServer $server,
        StatefulGuard $guard,
        ClientRepository $clients,
        private readonly ResourceIndicator $indicator,
    ) {
        parent::__construct($server, $guard, $clients);
    }

    #[\Override]
    public function authorize(
        ServerRequestInterface $psrRequest,
        Request $request,
        ResponseInterface $psrResponse,
        AuthorizationViewResponse $viewResponse,
    ): Response|AuthorizationViewResponse {
        // Refused before Passport validates the client: redirecting to a redirect_uri nobody checked yet would be
        // an open redirect, so the error is answered here.
        $resource = ProtectedResource::fromUri($request->query('resource'))
            ?? throw new OAuthServerException(InvalidTarget::because('The resource must name this server, e.g. ' . ProtectedResource::Mcp->uri() . '.'));

        $request->session()->put(self::RESOURCE, $resource->value);
        $this->indicator->indicate($resource);

        try {
            return parent::authorize($psrRequest, $request, $psrResponse, $viewResponse);
        } finally {
            $this->indicator->take();
        }
    }
}
