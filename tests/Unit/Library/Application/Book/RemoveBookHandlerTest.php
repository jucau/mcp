<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\RemoveBook\RemoveBook;
use App\Library\Application\Book\RemoveBook\RemoveBookHandler;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookLent;
use App\Library\Domain\Book\UnknownBook;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\Unit\Library\Application\UseCaseTestCase;

final class RemoveBookHandlerTest extends UseCaseTestCase
{
    #[Test]
    public function a_book_is_removed_from_the_library(): void
    {
        $id = $this->given(Books::aBook());

        $this->remove($id);

        $this->expectException(UnknownBook::class);

        $this->book($id);
    }

    #[Test]
    public function a_lent_book_stays_in_the_library(): void
    {
        $id = $this->given($this->lentTo('Marie'));

        try {
            $this->remove($id);
            $this->fail('A lent book was removed.');
        } catch (BookLent) {
            $this->assertSame('Marie', $this->book($id)->loan()?->borrower->value);
        }
    }

    #[Test]
    public function an_unknown_book_cannot_be_removed(): void
    {
        $this->expectException(UnknownBook::class);

        $this->remove(BookId::generate()->value);
    }

    private function remove(string $id): void
    {
        (new RemoveBookHandler($this->books))->handle(new RemoveBook($id));
    }
}
