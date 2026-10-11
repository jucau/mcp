<?php

declare(strict_types=1);

namespace Tests\Support\Library\Contract;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Catalog\CatalogEntry;
use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Domain\Book\Isbn;
use PHPUnit\Framework\Attributes\Test;

trait BookCatalogContract
{
    abstract protected function catalogKnowing(CatalogEntry $entry): BookCatalog;

    abstract protected function catalogDown(): BookCatalog;

    #[Test]
    public function a_known_isbn_gives_its_whole_entry(): void
    {
        $dune = new CatalogEntry('9780441172719', 'Dune', 'Frank Herbert', 1965, 896);

        $this->assertEquals($dune, $this->catalogKnowing($dune)->find(Isbn::fromString('9780441172719')));
    }

    #[Test]
    public function an_entry_may_know_nothing_but_its_title(): void
    {
        $anonymous = new CatalogEntry('9780441172719', 'Dune');

        $this->assertEquals($anonymous, $this->catalogKnowing($anonymous)->find(Isbn::fromString('9780441172719')));
    }

    #[Test]
    public function an_unknown_isbn_gives_nothing(): void
    {
        $catalog = $this->catalogKnowing(new CatalogEntry('9780441172719', 'Dune', 'Frank Herbert'));

        $this->assertNull($catalog->find(Isbn::fromString('9782070360024')));
    }

    #[Test]
    public function a_catalog_that_is_down_says_so(): void
    {
        $this->expectException(CatalogUnavailable::class);

        $this->catalogDown()->find(Isbn::fromString('9780441172719'));
    }
}
