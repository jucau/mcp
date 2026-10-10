<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth\Passport;

use App\Library\Infrastructure\Auth\ProtectedResource;
use App\Library\Infrastructure\Auth\ResourceIndicator;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;

final class AccessTokenRepository extends PassportAccessTokenRepository
{
    public function __construct(Dispatcher $events, private readonly ResourceIndicator $indicator)
    {
        parent::__construct($events);
    }

    #[\Override]
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        parent::persistNewAccessToken($accessTokenEntity);

        Passport::token()->newQuery()
            ->whereKey($accessTokenEntity->getIdentifier())
            ->update(['resource' => $this->indicator->take()?->uri()]);
    }

    /**
     * A refresh revokes the old token right before it issues the new one: the new token inherits its resource.
     */
    #[\Override]
    public function revokeAccessToken(string $tokenId): void
    {
        $this->indicator->indicate(ProtectedResource::fromUri(Passport::token()->newQuery()->whereKey($tokenId)->value('resource')));

        parent::revokeAccessToken($tokenId);
    }
}
