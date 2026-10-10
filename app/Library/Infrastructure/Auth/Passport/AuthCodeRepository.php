<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth\Passport;

use App\Library\Infrastructure\Auth\ResourceIndicator;
use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

final class AuthCodeRepository extends PassportAuthCodeRepository
{
    public function __construct(private readonly ResourceIndicator $indicator)
    {
    }

    #[\Override]
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        parent::persistNewAuthCode($authCodeEntity);

        Passport::authCode()->newQuery()
            ->whereKey($authCodeEntity->getIdentifier())
            ->update(['resource' => $this->indicator->take()?->uri()]);
    }
}
