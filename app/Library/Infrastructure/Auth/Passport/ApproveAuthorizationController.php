<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth\Passport;

use App\Library\Infrastructure\Auth\ProtectedResource;
use App\Library\Infrastructure\Auth\ResourceIndicator;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveAuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

final class ApproveAuthorizationController extends PassportApproveAuthorizationController
{
    public function __construct(AuthorizationServer $server, private readonly ResourceIndicator $indicator)
    {
        parent::__construct($server);
    }

    #[\Override]
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $resource = $request->session()->pull(AuthorizationController::RESOURCE);
        $this->indicator->indicate(is_string($resource) ? ProtectedResource::tryFrom($resource) : null);

        try {
            return parent::approve($request, $psrResponse);
        } finally {
            $this->indicator->take();
        }
    }
}
