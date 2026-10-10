<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Domain\Book\Borrower;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;

final class LoanTest extends ApiTestCase
{
    #[Test]
    public function a_book_is_lent_today_unless_told_otherwise(): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/lend', ['borrower' => ' Marie '])
            ->assertOk()
            ->assertJsonPath('data.lent_to', 'Marie')
            ->assertJsonPath('data.lent_on', '2026-09-27');
    }

    #[Test]
    public function a_book_is_lent_on_the_day_given(): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/lend', ['borrower' => 'Marie', 'on' => '2026-09-15'])
            ->assertOk()
            ->assertJsonPath('data.lent_on', '2026-09-15');
    }

    #[Test]
    public function a_book_lent_needs_a_borrower(): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/lend', ['borrower' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['borrower']);
    }

    #[Test]
    public function a_book_wished_for_cannot_be_lent(): void
    {
        $book = $this->given(Books::aBook(owned: false));

        $this->postJson('/api/books/' . $book->id()->value . '/lend', ['borrower' => 'Marie'])
            ->assertConflict()
            ->assertJsonPath('type', url('problems/book-not-owned'));
    }

    #[Test]
    public function a_book_lent_comes_back(): void
    {
        $book = Books::aBook();
        $book->lendTo(Borrower::fromString('Léa'), new DateTimeImmutable('2026-09-01'));
        $this->given($book);

        $this->postJson('/api/books/' . $book->id()->value . '/return')
            ->assertOk()
            ->assertJsonPath('data.lent_to', null)
            ->assertJsonPath('data.lent_on', null);
    }

    #[Test]
    public function a_book_at_home_cannot_come_back(): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/return')
            ->assertConflict()
            ->assertJsonPath('type', url('problems/book-not-lent'));
    }
}
