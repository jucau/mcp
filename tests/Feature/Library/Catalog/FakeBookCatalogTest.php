<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Catalog;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Catalog\CatalogEntry;
use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Domain\Book\Isbn;
use App\Library\Infrastructure\Catalog\FakeBookCatalog;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FakeBookCatalogTest extends TestCase
{
    #[Test]
    public function the_library_looks_books_up_in_the_fake_catalog_when_told_to(): void
    {
        config(['library.catalog' => 'fake']);

        $this->assertInstanceOf(FakeBookCatalog::class, $this->app->make(BookCatalog::class));
    }

    #[Test]
    public function it_never_serves_production(): void
    {
        config(['library.catalog' => 'fake']);
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->expectExceptionObject(new LogicException('The fake catalog must never serve production.'));

        $this->app->make(BookCatalog::class);
    }

    #[Test]
    public function a_catalog_that_does_not_exist_is_refused(): void
    {
        config(['library.catalog' => 'fak']);

        $this->expectExceptionObject(new LogicException('LIBRARY_CATALOG must be "open-library" or "fake".'));

        $this->app->make(BookCatalog::class);
    }

    #[Test]
    public function an_isbn_gets_an_entry_that_is_plainly_fake(): void
    {
        $this->assertEquals(
            new CatalogEntry('9782070360024', 'Fake book 9782070360024', 'Fake Author', 1974, 124),
            new FakeBookCatalog()->find(Isbn::fromString('2-07-036002-4')),
        );
    }

    #[Test]
    public function the_same_isbn_always_gets_the_same_entry_and_another_isbn_another_one(): void
    {
        $catalog = new FakeBookCatalog();

        $this->assertEquals($catalog->find(Isbn::fromString('9780306406157')), $catalog->find(Isbn::fromString('9780306406157')));
        $this->assertEquals(
            new CatalogEntry('9780306406157', 'Fake book 9780306406157', 'Fake Author', 1957, 257),
            $catalog->find(Isbn::fromString('9780306406157')),
        );
    }

    #[Test]
    public function an_isbn_ending_with_0_is_not_in_the_catalog(): void
    {
        $this->assertNull(new FakeBookCatalog()->find(Isbn::fromString('9780301000060')));
    }

    #[Test]
    public function an_isbn_ending_with_9_brings_the_catalog_down(): void
    {
        $this->expectException(CatalogUnavailable::class);

        new FakeBookCatalog()->find(Isbn::fromString('9780441172719'));
    }
}
