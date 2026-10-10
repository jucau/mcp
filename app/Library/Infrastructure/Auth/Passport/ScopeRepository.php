<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth\Passport;

use App\Library\Infrastructure\Auth\ProtectedResource;
use App\Library\Infrastructure\Auth\ResourceIndicator;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * The code exchange calls finalizeScopes with the code id right before it issues the token: the only point where the
 * resource granted with the code can be read back in time.
 */
final class ScopeRepository extends PassportScopeRepository
{
    public function __construct(ClientRepository $clients, private readonly ResourceIndicator $indicator)
    {
        parent::__construct($clients);
    }

    #[\Override]
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null,
    ): array {
        if ($authCodeId !== null) {
            $this->indicator->indicate($this->resourceGrantedWith($authCodeId));
        }

        return parent::finalizeScopes($scopes, $grantType, $clientEntity, $userIdentifier, $authCodeId);
    }

    private function resourceGrantedWith(string $authCodeId): ?ProtectedResource
    {
        $resource = ProtectedResource::fromUri(Passport::authCode()->newQuery()->whereKey($authCodeId)->value('resource'));
        $requested = request()->input('resource');

        if ($requested !== null && $requested !== $resource?->uri()) {
            throw InvalidTarget::because('The resource must be the one the code was granted for.');
        }

        return $resource;
    }
}
