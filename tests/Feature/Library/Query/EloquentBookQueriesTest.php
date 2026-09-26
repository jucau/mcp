<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Query;

use App\Library\Application\Book\Query\BookSearch;
use App\Library\Application\Book\Query\BookView;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\Borrower;
use App\Library\Domain\Book\InvalidBookId;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\Rating;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Infrastructure\Persistence\Eloquent\Queries\EloquentBookQueries;
use App\Library\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\TestCase;

final class EloquentBookQueriesTest extends TestCase
{
    use DatabaseTransactions;

    private EloquentBookQueries $queries;

    private EloquentBookRepository $books;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queries = new EloquentBookQueries();
        $this->books = new EloquentBookRepository();
    }

    #[Test]
    public function a_book_is_found_with_everything_the_library_knows_about_it(): void
    {
        $book = $this->add('Dune', 'Frank Herbert', '9780441172719');
        $book->startReading(new DateTimeImmutable('2026-01-10'));
        $book->finishReading(new DateTimeImmutable('2026-02-01'));
        $book->rate(Rating::fromInt(5));
        $book->lendTo(Borrower::fromString('Marie'), new DateTimeImmutable('2026-03-15'));

        $this->books->save($book);

        $this->assertEquals(
            new BookView($book->id()->value, 'Dune', 'Frank Herbert', '9780441172719', true, 'read', '2026-01-10', '2026-02-01', 5, 'Marie', '2026-03-15'),
            $this->queries->find($book->id()->value),
        );
    }

    #[Test]
    public function an_unknown_id_finds_nothing(): void
    {
        $this->assertNull($this->queries->find(BookId::generate()->value));
    }

    #[Test]
    public function a_malformed_id_is_refused_like_in_the_use_cases(): void
    {
        $this->expectException(InvalidBookId::class);

        $this->queries->find('dune');
    }

    #[Test]
    public function the_text_matches_title_or_author_whatever_the_case(): void
    {
        $this->add('Dune', 'Frank Herbert');
        $this->add('Fondation', 'Isaac Asimov');
        $this->add('Les Robots', 'Isaac Asimov');

        $this->assertSame(['Dune'], $this->titles(new BookSearch(text: 'dUNE')));
        $this->assertSame(['Fondation', 'Les Robots'], $this->titles(new BookSearch(text: 'asimov')));
    }

    #[Test]
    public function the_text_matches_whatever_the_accents(): void
    {
        $this->add('Hypérion', 'Dan Simmons');
        $this->add('Cent ans de solitude', 'Gabriel García Márquez');
        $this->add('Dune', 'Frank Herbert');

        $this->assertSame(['Hypérion'], $this->titles(new BookSearch(text: 'hyperion')));
        $this->assertSame(['Cent ans de solitude'], $this->titles(new BookSearch(text: 'garcia marquez')));
        $this->assertSame(['Dune'], $this->titles(new BookSearch(text: 'dûne')));
    }

    #[Test]
    public function every_word_of_the_text_must_match_the_title_or_the_author(): void
    {
        $this->add('Le Seigneur des anneaux', 'J. R. R. Tolkien');
        $this->add('Bilbo le Hobbit', 'J. R. R. Tolkien');
        $this->add('Les Anneaux de Saturne', 'W. G. Sebald');

        $this->assertSame(['Le Seigneur des anneaux'], $this->titles(new BookSearch(text: 'tolkien anneaux')));
        $this->assertSame(['Le Seigneur des anneaux'], $this->titles(new BookSearch(text: '  anneaux   seigneur ')));
        $this->assertSame([], $this->titles(new BookSearch(text: 'tolkien saturne')));
    }

    #[Test]
    public function the_text_never_looks_into_isbns(): void
    {
        $this->add('1984', 'George Orwell');
        $this->add('Dune', 'Frank Herbert', '9781984000019');

        $this->assertSame(['1984'], $this->titles(new BookSearch(text: '1984')));
    }

    #[Test]
    public function an_isbn_finds_its_book_whether_typed_as_isbn_10_or_isbn_13(): void
    {
        $this->add('Dune', 'Frank Herbert', '9780441172719');
        $this->add('Fondation', 'Isaac Asimov', '9782070360024');

        $this->assertSame(['Dune'], $this->titles(new BookSearch(isbn: Isbn::fromString('978-0-441-17271-9'))));
        $this->assertSame(['Dune'], $this->titles(new BookSearch(isbn: Isbn::fromString('0-441-17271-7'))));
    }

    #[Test]
    public function an_isbn_matches_exactly_never_partially(): void
    {
        $this->add('Dune', 'Frank Herbert', '9780441172719');
        $this->add('Sans ISBN', 'Anonyme');

        $this->assertSame([], $this->titles(new BookSearch(isbn: Isbn::fromString('9780306406157'))));
    }

    #[Test]
    public function an_isbn_combines_with_the_other_criteria(): void
    {
        $this->add('Dune', 'Frank Herbert', '9780441172719');

        $this->assertSame([], $this->titles(new BookSearch(text: 'asimov', isbn: Isbn::fromString('9780441172719'))));
        $this->assertSame(['Dune'], $this->titles(new BookSearch(text: 'herbert', isbn: Isbn::fromString('9780441172719'))));
    }

    #[Test]
    public function like_wildcards_in_the_text_are_matched_literally(): void
    {
        $this->add('Dune', 'Frank Herbert');

        $this->assertSame([], $this->titles(new BookSearch(text: '%')));
    }

    #[Test]
    public function the_shelves_filter_on_reading_status_ownership_and_loans(): void
    {
        $this->add('Dune', 'Frank Herbert');
        $reading = $this->add('Fondation', 'Isaac Asimov');
        $reading->startReading(new DateTimeImmutable('2026-09-01'));
        $reading->lendTo(Borrower::fromString('Paul'), new DateTimeImmutable('2026-09-10'));

        $this->books->save($reading);
        $this->add('Hypérion', 'Dan Simmons', owned: false);

        $this->assertSame(['Dune', 'Hypérion'], $this->titles(new BookSearch(readingStatus: ReadingStatus::ToRead)));
        $this->assertSame(['Fondation'], $this->titles(new BookSearch(readingStatus: ReadingStatus::Reading)));
        $this->assertSame(['Hypérion'], $this->titles(new BookSearch(owned: false)));
        $this->assertSame(['Fondation'], $this->titles(new BookSearch(lent: true)));
        $this->assertSame(['Dune', 'Hypérion'], $this->titles(new BookSearch(lent: false)));
        $this->assertSame(['Dune'], $this->titles(new BookSearch(readingStatus: ReadingStatus::ToRead, owned: true)));
    }

    #[Test]
    public function results_are_paginated_by_title(): void
    {
        foreach (['Ubik', 'Dune', 'Solaris', 'Fondation', 'Neuromancien'] as $title) {
            $this->add($title, 'Divers');
        }

        $page = $this->queries->search(new BookSearch(page: 2, perPage: 2));

        $this->assertSame(['Neuromancien', 'Solaris'], array_map(static fn (BookView $book): string => $book->title, $page->items));
        $this->assertSame(2, $page->page);
        $this->assertSame(2, $page->perPage);
        $this->assertSame(5, $page->total);
    }

    #[Test]
    public function finished_in_a_year_matches_the_books_read_or_abandoned_that_year(): void
    {
        $this->finished('Dune', '2025-01-01', '2025-12-31');
        $this->finished('Fondation', '2025-06-01', '2026-01-01');
        $abandoned = $this->add('Ulysse', 'James Joyce');
        $abandoned->startReading(new DateTimeImmutable('2024-11-01'));
        $abandoned->abandon(new DateTimeImmutable('2025-02-01'));

        $this->books->save($abandoned);
        $reading = $this->add('Hypérion', 'Dan Simmons');
        $reading->startReading(new DateTimeImmutable('2025-03-01'));

        $this->books->save($reading);

        $this->assertSame(['Dune', 'Ulysse'], $this->titles(new BookSearch(finishedIn: 2025)));
        $this->assertSame(['Fondation'], $this->titles(new BookSearch(finishedIn: 2026)));
    }

    #[Test]
    public function all_returns_every_matching_book_by_title_whatever_the_page(): void
    {
        for ($i = 105; $i >= 1; --$i) {
            $this->add(sprintf('Tome %03d', $i), 'Divers');
        }

        $this->add('Hypérion', 'Dan Simmons', owned: false);

        $titles = array_map(static fn (BookView $book): string => $book->title, $this->queries->all(new BookSearch(owned: true, page: 3, perPage: 1)));

        $this->assertCount(105, $titles);
        $this->assertSame(['Tome 001', 'Tome 002'], array_slice($titles, 0, 2));
        $this->assertSame('Tome 105', $titles[104]);
    }

    private function finished(string $title, string $startedOn, string $finishedOn): void
    {
        $book = $this->add($title, 'Divers');
        $book->startReading(new DateTimeImmutable($startedOn));
        $book->finishReading(new DateTimeImmutable($finishedOn));

        $this->books->save($book);
    }

    private function add(string $title, string $author, ?string $isbn = null, bool $owned = true): Book
    {
        $book = Books::aBook($title, $author, $isbn, $owned);
        $this->books->save($book);

        return $book;
    }

    /**
     * @return list<string>
     */
    private function titles(BookSearch $search): array
    {
        return array_map(static fn (BookView $book): string => $book->title, $this->queries->search($search)->items);
    }
}
