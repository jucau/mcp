<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth\Passport;

use League\OAuth2\Server\Exception\OAuthServerException;

final class InvalidTarget
{
    public static function because(string $reason): OAuthServerException
    {
        return new OAuthServerException($reason, 0, 'invalid_target');
    }
}
