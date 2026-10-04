<?php

declare(strict_types=1);

namespace Tests;

use App\Library\Infrastructure\Reader\ConfiguredReader;
use App\Library\Infrastructure\Reader\CurrentReader;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\Library\Readers;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['library.reader' => Readers::ME]);
        $this->app->bind(CurrentReader::class, ConfiguredReader::class);
    }
}
