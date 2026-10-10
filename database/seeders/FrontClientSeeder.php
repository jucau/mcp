<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Seeder;
use Laravel\Passport\Passport;
use Webmozart\Assert\Assert;

final class FrontClientSeeder extends Seeder
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function run(): void
    {
        $id = $this->config->get('library.front_client_id');
        $frontUrl = $this->config->get('library.front_url');
        Assert::stringNotEmpty($id);
        Assert::stringNotEmpty($frontUrl);

        Passport::client()->forceFill([
            'id' => $id,
            'name' => 'Marque-page web',
            'secret' => null,
            'redirect_uris' => [$frontUrl . '/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ])->save();
    }
}
