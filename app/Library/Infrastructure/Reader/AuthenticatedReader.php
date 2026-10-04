<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Reader;

use App\Library\Domain\Book\ReaderId;
use App\Library\Infrastructure\Auth\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;

final readonly class AuthenticatedReader implements CurrentReader
{
    public function __construct(private Factory $auth)
    {
    }

    public function id(): ReaderId
    {
        return ReaderId::fromString($this->account()->reader_id);
    }

    public function timezone(): string
    {
        return $this->account()->timezone;
    }

    private function account(): User
    {
        $account = $this->auth->guard('api')->user();

        if (! $account instanceof User) {
            throw new AuthenticationException('No account behind this request: the reader cannot be known.', ['api']);
        }

        return $account;
    }
}
