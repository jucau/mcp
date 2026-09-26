<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Borrower;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Tools\SearchBooksTool;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Announced;
use Tests\Support\Library\Books;
use Tests\Support\Library\BookSchema;
use Tests\Support\Library\OutputSchema;
use Tests\TestCase;

final class SearchBooksToolTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_announces_its_contract_to_the_model(): void
    {
        $this->assertEquals([
            'name' => 'search_books',
            'title' => 'Search books',
            'description' => <<<'TEXT'
                Search the user's library. Every criterion is optional and they combine: with none, it lists every book.
                Use it to find a book's id before acting on it, or to answer questions such as "what am I reading?",
                "what did I lend?" or "which books are on my wishlist?". Results are sorted by title and paginated.
                TEXT,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Words to look for in the title or the author. Every word must match; case and accents are ignored.',
                    ],
                    'isbn' => [
                        'type' => 'string',
                        'description' => 'The exact ISBN of the book, ISBN-10 or ISBN-13, hyphens allowed.',
                    ],
                    'reading_status' => [
                        'type' => 'string',
                        'enum' => ['to_read', 'reading', 'read', 'abandoned'],
                        'description' => 'Only books at this stage: to_read, reading, read or abandoned.',
                    ],
                    'owned' => [
                        'type' => 'boolean',
                        'description' => 'true: only books the user owns. false: only their wishlist.',
                    ],
                    'lent' => [
                        'type' => 'boolean',
                        'description' => 'true: only books currently lent to someone. false: only books at home.',
                    ],
                    'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                ],
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'total' => ['type' => 'integer', 'description' => 'How many books match, across all pages.'],
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'books' => ['type' => 'array', 'items' => BookSchema::BOOK],
                ],
                'required' => ['total', 'page', 'per_page', 'books'],
            ],
            'annotations' => [
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => false,
            ],
        ], Announced::tool('search_books'));
    }

    #[Test]
    public function it_returns_the_matching_books_with_everything_the_model_needs_to_act_on_them(): void
    {
        $dune = Books::aBook(isbn: '9780441172719');
        $dune->startReading(new DateTimeImmutable('2026-09-01'));
        $dune->lendTo(Borrower::fromString('Marie'), new DateTimeImmutable('2026-09-10'));
        $this->given($dune);
        $this->given(Books::aBook('Hypérion', 'Dan Simmons'));

        $response = LibraryServer::tool(SearchBooksTool::class, ['query' => 'herbert'])
            ->assertOk()
            ->assertStructuredContent([
                'total' => 1,
                'page' => 1,
                'per_page' => 20,
                'books' => [[
                    'id' => $dune->id()->value,
                    'title' => 'Dune',
                    'author' => 'Frank Herbert',
                    'isbn' => '9780441172719',
                    'owned' => true,
                    'reading_status' => 'reading',
                    'started_on' => '2026-09-01',
                    'finished_on' => null,
                    'rating' => null,
                    'lent_to' => 'Marie',
                    'lent_on' => '2026-09-10',
                ]],
            ]);

        OutputSchema::assertRespected($response, 'search_books');
    }

    #[Test]
    public function every_criterion_reaches_the_search(): void
    {
        $this->given(Books::aBook('Hypérion', 'Dan Simmons', owned: false));
        $this->given(Books::aBook('Fondation', 'Isaac Asimov', owned: false));
        $this->given(Books::aBook('Dune', 'Frank Herbert'));

        LibraryServer::tool(SearchBooksTool::class, [
            'reading_status' => 'to_read',
            'owned' => false,
            'lent' => false,
            'page' => 2,
            'per_page' => 1,
        ])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $result): AssertableJson => $result
                ->where('total', 2)
                ->where('page', 2)
                ->where('per_page', 1)
                ->has('books', 1, fn (AssertableJson $book): AssertableJson => $book->where('title', 'Hypérion')->etc()));
    }

    #[Test]
    public function an_isbn_finds_its_book_even_typed_as_isbn_10(): void
    {
        $this->given(Books::aBook(isbn: '9780441172719'));
        $this->given(Books::aBook('Fondation', 'Isaac Asimov', '9782070360024'));

        LibraryServer::tool(SearchBooksTool::class, ['isbn' => '0-441-17271-7'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $result): AssertableJson => $result
                ->where('total', 1)
                ->has('books.0', fn (AssertableJson $book): AssertableJson => $book->where('title', 'Dune')->etc())
                ->etc());
    }

    #[Test]
    public function an_invalid_isbn_is_refused_with_a_message_the_model_can_act_on(): void
    {
        config(['app.debug' => false]);

        LibraryServer::tool(SearchBooksTool::class, ['isbn' => '0-441-17271-0'])
            ->assertHasErrors(['"0-441-17271-0" is not a valid ISBN-10 or ISBN-13.']);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[Test]
    #[TestWith([['reading_status' => 'finished'], 'reading status'])]
    #[TestWith([['isbn' => 9780441172719], 'isbn'])]
    #[TestWith([['owned' => 'false'], 'owned'])]
    #[TestWith([['per_page' => '20'], 'per page'])]
    #[TestWith([['per_page' => 101], 'per page'])]
    #[TestWith([['page' => 0], 'page'])]
    public function malformed_arguments_are_refused_with_a_message_the_model_can_fix(array $arguments, string $field): void
    {
        LibraryServer::tool(SearchBooksTool::class, $arguments)->assertHasErrors([$field]);
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
