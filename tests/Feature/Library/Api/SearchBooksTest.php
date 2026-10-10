<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Domain\Book\Borrower;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;

final class SearchBooksTest extends ApiTestCase
{
    #[Test]
    public function it_lists_the_books_of_the_reader_only_sorted_by_title_with_the_pagination(): void
    {
        $this->given(Books::aBook('Fondation', 'Isaac Asimov'));
        $this->given(Books::aBook('Dune', 'Frank Herbert', '9780441172719'));
        $this->given(Books::aBook('Hypérion', 'Dan Simmons', reader: Readers::someoneElse()));

        $this->getJson('/api/books?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Dune')
            ->assertJsonPath('data.0.isbn', '9780441172719')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('links.next', url('/api/books?per_page=1&page=2'));
    }

    #[Test]
    public function every_field_of_a_book_is_presented(): void
    {
        $book = Books::aBook('Dune', 'Frank Herbert', '9780441172719');
        $book->startReading(new DateTimeImmutable('2026-09-01'));
        $this->given($book);

        $this->getJson('/api/books')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => $book->id()->value,
                    'title' => 'Dune',
                    'author' => 'Frank Herbert',
                    'isbn' => '9780441172719',
                    'owned' => true,
                    'reading_status' => 'reading',
                    'started_on' => '2026-09-01',
                    'finished_on' => null,
                    'rating' => null,
                    'lent_to' => null,
                    'lent_on' => null,
                ]],
                'links' => [
                    'first' => url('/api/books?page=1'),
                    'last' => url('/api/books?page=1'),
                    'prev' => null,
                    'next' => null,
                ],
                'meta' => [
                    'current_page' => 1,
                    'from' => 1,
                    'last_page' => 1,
                    'links' => [
                        ['url' => null, 'label' => '&laquo; Previous', 'page' => null, 'active' => false],
                        ['url' => url('/api/books?page=1'), 'label' => '1', 'page' => 1, 'active' => true],
                        ['url' => null, 'label' => 'Next &raquo;', 'page' => null, 'active' => false],
                    ],
                    'path' => url('/api/books'),
                    'per_page' => 20,
                    'to' => 1,
                    'total' => 1,
                ],
            ]);
    }

    #[Test]
    public function the_criteria_of_the_query_string_combine(): void
    {
        $this->given(Books::aBook('Dune', 'Frank Herbert'));
        $this->given(Books::aBook('Dune Messiah', 'Frank Herbert', owned: false));
        $this->given(Books::aBook('Fondation', 'Isaac Asimov', owned: false));

        $this->getJson('/api/books?query=herbert&owned=false&reading_status=to_read')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Dune Messiah');
    }

    /**
     * @param list<string> $titles
     */
    #[Test]
    #[TestWith(['owned=true', ['Dune', 'Neuromancien']])]
    #[TestWith(['owned=false', ['Hypérion']])]
    #[TestWith(['lent=true', ['Neuromancien']])]
    #[TestWith(['lent=false', ['Dune', 'Hypérion']])]
    public function true_and_false_are_the_only_booleans_of_the_query_string(string $query, array $titles): void
    {
        $this->given(Books::aBook('Dune', 'Frank Herbert'));
        $lent = Books::aBook('Neuromancien', 'William Gibson');
        $lent->lendTo(Borrower::fromString('Léa'), new DateTimeImmutable('2026-09-01'));
        $this->given($lent);
        $this->given(Books::aBook('Hypérion', 'Dan Simmons', owned: false));

        $response = $this->getJson('/api/books?' . $query)->assertOk();

        $this->assertSame($titles, $response->collect('data')->pluck('title')->all());
    }

    #[Test]
    #[TestWith(['owned=yes'])]
    #[TestWith(['owned=1'])]
    #[TestWith(['lent=TRUE'])]
    #[TestWith(['reading_status=finished'])]
    #[TestWith(['page=0'])]
    #[TestWith(['per_page=101'])]
    public function a_query_string_it_cannot_read_is_refused_as_a_problem(string $query): void
    {
        $this->getJson('/api/books?' . $query)
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', url('problems/invalid-request'))
            ->assertJsonPath('status', 422)
            ->assertJsonStructure(['errors']);
    }

    #[Test]
    public function an_isbn_the_domain_refuses_comes_back_with_its_message(): void
    {
        $this->getJson('/api/books?isbn=123')
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertExactJson([
                'type' => url('problems/invalid-isbn'),
                'title' => 'Invalid isbn',
                'status' => 422,
                'detail' => '"123" is not a valid ISBN-10 or ISBN-13.',
            ]);
    }
}
