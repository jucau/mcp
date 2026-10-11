<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Webmozart\Assert\Assert;

final class ContractTest extends TestCase
{
    #[Test]
    public function the_versioned_contract_is_the_one_the_code_describes(): void
    {
        $exported = tempnam(sys_get_temp_dir(), 'openapi');
        Assert::string($exported);

        try {
            $this->assertSame(0, Artisan::call('scramble:export', ['--path' => $exported]));

            $this->assertJsonFileEqualsJsonFile(base_path('docs/api/openapi.json'), $exported, 'The API changed without its contract: run make openapi.');
        } finally {
            unlink($exported);
        }
    }
}
