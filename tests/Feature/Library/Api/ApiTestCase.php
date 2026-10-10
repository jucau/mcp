<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Application\Clock;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Auth\ProtectedResource;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\FrozenClock;
use Tests\Support\Library\Readers;
use Tests\Support\Library\Tokens;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2026-09-27')));
        Tokens::actingAs(Accounts::of(Readers::ME), ProtectedResource::Api);
    }

    protected function given(Book $book): Book
    {
        $this->app->make(BookRepository::class)->save($book);

        return $book;
    }
}
