<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Domain\Book\Borrower;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;

final class RemoveBookTest extends ApiTestCase
{
    #[Test]
    public function it_removes_the_book_for_good_and_answers_with_no_content(): void
    {
        $book = $this->given(Books::aBook());

        $this->deleteJson('/api/books/' . $book->id()->value)->assertNoContent();

        $this->assertNull($this->app->make(BookQueries::class)->find(Readers::me(), $book->id()->value));
        $this->deleteJson('/api/books/' . $book->id()->value)->assertNotFound();
    }

    #[Test]
    public function a_book_lent_cannot_be_removed(): void
    {
        $book = Books::aBook();
        $book->lendTo(Borrower::fromString('Léa'), new DateTimeImmutable('2026-09-01'));
        $this->given($book);

        $this->deleteJson('/api/books/' . $book->id()->value)
            ->assertConflict()
            ->assertJsonPath('type', url('problems/book-lent'));
    }

    #[Test]
    public function the_book_of_another_reader_is_unknown_and_stays_in_their_library(): void
    {
        $book = $this->given(Books::aBook(reader: Readers::someoneElse()));

        $this->deleteJson('/api/books/' . $book->id()->value)->assertNotFound();

        $this->assertInstanceOf(BookView::class, $this->app->make(BookQueries::class)->find(Readers::someoneElse(), $book->id()->value));
    }
}
