<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\AcquireBook\AcquireBook;
use App\Library\Application\Book\AcquireBook\AcquireBookHandler;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\UnknownBook;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\Unit\Library\Application\UseCaseTestCase;

final class AcquireBookHandlerTest extends UseCaseTestCase
{
    #[Test]
    public function a_wishlist_book_becomes_owned(): void
    {
        $id = $this->given(Books::aBook(owned: false));

        $this->acquire($id);

        $this->assertTrue($this->book($id)->isOwned());
    }

    #[Test]
    public function acquiring_an_owned_book_changes_nothing(): void
    {
        $id = $this->given(Books::aBook());

        $this->acquire($id);

        $this->assertTrue($this->book($id)->isOwned());
    }

    #[Test]
    public function an_unknown_book_cannot_be_acquired(): void
    {
        $this->expectException(UnknownBook::class);

        $this->acquire(BookId::generate()->value);
    }

    private function acquire(string $id): void
    {
        (new AcquireBookHandler($this->books))->handle(new AcquireBook($id));
    }
}
