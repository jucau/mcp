<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\Catalog\CatalogEntry;
use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Application\Book\Catalog\NotInCatalog;
use App\Library\Application\Book\LookUpBook\LookUpBook;
use App\Library\Application\Book\LookUpBook\LookUpBookHandler;
use App\Library\Domain\Book\InvalidIsbn;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tests\Support\Library\InMemoryBookCatalog;

final class LookUpBookHandlerTest extends TestCase
{
    private InMemoryBookCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new InMemoryBookCatalog();
    }

    #[Test]
    public function the_entry_of_a_book_the_catalog_knows_is_given_back(): void
    {
        $dune = new CatalogEntry('9780441172719', 'Dune', 'Frank Herbert', 1965, 896);
        $this->catalog->knows($dune, new CatalogEntry('9782070360024', "L'Étranger", 'Albert Camus'));

        $this->assertSame($dune, $this->lookUp('9780441172719'));
    }

    #[Test]
    #[TestWith(['0-441-17271-7'])]
    #[TestWith(['978-0-441-17271-9'])]
    #[TestWith([' 9780441172719 '])]
    public function the_catalog_is_always_asked_for_the_isbn_13(string $typed): void
    {
        $this->catalog->knows(new CatalogEntry('9780441172719', 'Dune'));

        $this->lookUp($typed);

        $this->assertSame(['9780441172719'], $this->catalog->asked());
    }

    #[Test]
    public function an_invalid_isbn_is_refused_before_the_catalog_is_asked(): void
    {
        try {
            $this->lookUp('9780441172710');
            $this->fail('An ISBN with a wrong check digit was looked up.');
        } catch (InvalidIsbn $invalidIsbn) {
            $this->assertSame('"9780441172710" is not a valid ISBN-10 or ISBN-13.', $invalidIsbn->getMessage());
        }

        $this->assertSame([], $this->catalog->asked());
    }

    #[Test]
    public function an_isbn_the_catalog_does_not_know_is_said_so_with_the_isbn_13(): void
    {
        $this->expectExceptionObject(new NotInCatalog('The open catalog knows no book with ISBN 9780441172719.'));

        $this->lookUp('0-441-17271-7');
    }

    #[Test]
    public function a_catalog_that_is_down_is_said_so(): void
    {
        $this->catalog->goDown();

        $this->expectExceptionObject(new CatalogUnavailable('The open catalog cannot be reached right now.'));

        $this->lookUp('9780441172719');
    }

    private function lookUp(string $isbn): CatalogEntry
    {
        return new LookUpBookHandler($this->catalog)->handle(new LookUpBook($isbn));
    }
}
