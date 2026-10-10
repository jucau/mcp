<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book\Query;

use App\Library\Application\Book\Query\BookSearch;
use App\Library\Application\Book\Query\Shelf;
use App\Library\Domain\Book\ReadingStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShelfTest extends TestCase
{
    #[Test]
    public function the_shelf_to_read_holds_the_books_owned_at_home_and_not_started(): void
    {
        $this->assertEquals(new BookSearch(readingStatus: ReadingStatus::ToRead, owned: true, lent: false), Shelf::ToRead->search());
    }

    #[Test]
    public function the_shelf_being_read_holds_every_book_being_read_even_lent_or_wished(): void
    {
        $this->assertEquals(new BookSearch(readingStatus: ReadingStatus::Reading), Shelf::Reading->search());
    }

    #[Test]
    public function the_lent_shelf_holds_every_book_lent_whatever_its_reading(): void
    {
        $this->assertEquals(new BookSearch(lent: true), Shelf::Lent->search());
    }

    #[Test]
    public function the_wishlist_holds_every_book_not_owned(): void
    {
        $this->assertEquals(new BookSearch(owned: false), Shelf::Wishlist->search());
    }
}
