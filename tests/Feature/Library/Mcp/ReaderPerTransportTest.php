<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Reader\AuthenticatedReader;
use App\Library\Infrastructure\Reader\ConfiguredReader;
use App\Library\Infrastructure\Reader\CurrentReader;
use Illuminate\Contracts\Container\BindingResolutionException;
use Laravel\Mcp\Server\Transport\HttpTransport;
use Laravel\Mcp\Server\Transport\StdioTransport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReaderPerTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        unset($this->app[CurrentReader::class]);
    }

    #[Test]
    public function no_entry_point_gets_a_reader_it_did_not_choose(): void
    {
        $this->expectException(BindingResolutionException::class);

        $this->app->make(CurrentReader::class);
    }

    #[Test]
    public function over_stdio_the_reader_comes_from_the_environment(): void
    {
        (new LibraryServer(new StdioTransport()))->start();

        $this->assertInstanceOf(ConfiguredReader::class, $this->app->make(CurrentReader::class));
    }

    #[Test]
    public function over_http_the_reader_comes_from_the_token(): void
    {
        (new LibraryServer(new HttpTransport(request())))->start();

        $this->assertInstanceOf(AuthenticatedReader::class, $this->app->make(CurrentReader::class));
    }
}
