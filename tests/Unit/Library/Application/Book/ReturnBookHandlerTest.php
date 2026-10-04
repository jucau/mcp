<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\ReturnBook\ReturnBook;
use App\Library\Application\Book\ReturnBook\ReturnBookHandler;
use App\Library\Domain\Book\BookNotLent;
use App\Library\Domain\Book\UnknownBook;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Tests\Unit\Library\Application\UseCaseTestCase;

final class ReturnBookHandlerTest extends UseCaseTestCase
{
    #[Test]
    public function a_returned_book_is_no_longer_lent(): void
    {
        $id = $this->given($this->lentTo('Marie'));

        $this->giveBack($id);

        $this->assertNull($this->book($id)->loan());
    }

    #[Test]
    public function a_book_that_is_not_lent_cannot_be_returned(): void
    {
        $id = $this->given(Books::aBook());

        $this->expectException(BookNotLent::class);

        $this->giveBack($id);
    }

    #[Test]
    public function the_book_of_another_reader_cannot_be_given_back(): void
    {
        $id = $this->given($this->lentTo('Marie', Readers::someoneElse()));

        $this->expectException(UnknownBook::class);

        $this->giveBack($id);
    }

    private function giveBack(string $id): void
    {
        (new ReturnBookHandler($this->books))->handle(new ReturnBook(Readers::ME, $id));
    }
}
