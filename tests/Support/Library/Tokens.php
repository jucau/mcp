<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Infrastructure\Auth\ProtectedResource;
use App\Library\Infrastructure\Auth\User;
use Laravel\Passport\AccessToken;

final class Tokens
{
    public static function actingAs(User $account, ?ProtectedResource $for = ProtectedResource::Mcp): void
    {
        $account->withAccessToken(new AccessToken([
            'oauth_user_id' => $account->getAuthIdentifier(),
            'oauth_scopes' => ['mcp:use'],
            'resource' => $for?->uri(),
        ]));

        auth()->guard('api')->setUser($account);
        auth()->shouldUse('api');
    }
}
