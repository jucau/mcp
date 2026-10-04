<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Library\Infrastructure\Auth\User;
use Illuminate\Database\Seeder;

final class AccountSeeder extends Seeder
{
    public const string PASSWORD = 'marque-page';

    public function run(): void
    {
        $this->account('Julien', 'julien@marque-page.test', LibrarySeeder::JULIEN);
        $this->account('Camille', 'camille@marque-page.test', LibrarySeeder::CAMILLE);
    }

    private function account(string $name, string $email, string $readerId): void
    {
        $account = new User();
        $account->name = $name;
        $account->email = $email;
        $account->password = self::PASSWORD;
        $account->reader_id = $readerId;
        $account->timezone = 'Europe/Paris';
        $account->save();
    }
}
