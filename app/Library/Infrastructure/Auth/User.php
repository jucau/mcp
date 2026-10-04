<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Passport\Client;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Token;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $reader_id
 * @property string $timezone
 * @property-read Collection<int, Client> $clients
 * @property-read Collection<int, Client> $oauthApps
 * @property-read Collection<int, Token> $tokens
 * @mixin \Eloquent
 */
final class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;

    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
