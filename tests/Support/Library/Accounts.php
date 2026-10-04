<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Infrastructure\Auth\User;

final class Accounts
{
    public static function of(string $readerId, string $timezone = 'Europe/Paris'): User
    {
        $account = User::query()->where('reader_id', $readerId)->first() ?? new User();
        $account->name = 'Reader';
        $account->email = $readerId . '@marque-page.test';
        $account->password = 'secret';
        $account->reader_id = $readerId;
        $account->timezone = $timezone;
        $account->save();

        return $account;
    }
}
