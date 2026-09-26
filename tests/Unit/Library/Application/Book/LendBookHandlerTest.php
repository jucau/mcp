<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\LendBook\LendBook;
use App\Library\Application\Book\LendBook\LendBookHandler;
use App\Library\Domain\Book\BookAlreadyLent;
use App\Library\Domain\Book\BookNotOwned;
use App\Library\Domain\Book\Borrower;
use App\Library\Domain\Book\InvalidText;
use App\Library\Domain\Book\Loan;
use App\Library\Domain\Book\Title;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\Unit\Library\Application\UseCaseTestCase;

final class LendBookHandlerTest extends UseCaseTestCase
{
    #[Test]
    public function an_owned_book_is_lent_today_by_default(): void
    {
        $id = $this->given(Books::aBook());

        $this->lend($id, ' Marie ');

        $this->assertEquals(new Loan(Borrower::fromString('Marie'), self::day(self::TODAY)), $this->book($id)->loan());
    }

    #[Test]
    public function a_book_is_lent_on_the_given_day(): void
    {
        $id = $this->given(Books::aBook());

        $this->lend($id, 'Marie', self::day('2026-06-01'));

        $this->assertEquals(self::day('2026-06-01'), $this->book($id)->loan()?->lentOn);
    }

    #[Test]
    public function a_book_is_lent_to_someone_named(): void
    {
        $id = $this->given(Books::aBook());

        $this->expectException(InvalidText::class);

        $this->lend($id, '  ');
    }

    #[Test]
    public function a_wishlist_book_is_not_lent(): void
    {
        $id = $this->given(Books::aBook(owned: false));

        $this->expectException(BookNotOwned::class);

        $this->lend($id, 'Marie');
    }

    #[Test]
    public function a_lent_book_is_not_lent_again_until_returned(): void
    {
        $id = $this->given($this->lentTo('Marie'));

        $this->expectExceptionObject(BookAlreadyLent::to(Title::fromString('Dune'), Borrower::fromString('Marie')));

        $this->lend($id, 'Paul');
    }

    private function lend(string $id, string $borrower, ?DateTimeImmutable $on = null): void
    {
        (new LendBookHandler($this->books, $this->clock))->handle(new LendBook($id, $borrower, $on));
    }
}
