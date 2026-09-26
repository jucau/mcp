<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Tools\RemoveBookTool;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Announced;
use Tests\Support\Library\Books;
use Tests\TestCase;

final class RemoveBookToolTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_announces_its_contract_to_the_model(): void
    {
        $this->assertEquals([
            'name' => 'remove_book',
            'title' => 'Remove a book',
            'description' => <<<'TEXT'
                Remove a book from the user's library for good, with its reading history, rating and dates. Only when
                the user explicitly asks to remove it: this is not how to mark a book as read or abandoned. Refused
                while the book is lent. Returns the removed book as it was, so you can tell the user what went.
                TEXT,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'book_id' => [
                        'type' => 'string',
                        'format' => 'uuid',
                        'description' => 'The id of the book, as returned by search_books.',
                    ],
                ],
                'required' => ['book_id'],
            ],
            'annotations' => [
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => false,
            ],
        ], Announced::tool('remove_book'));
    }

    #[Test]
    public function it_removes_the_book_and_returns_it_as_it_was(): void
    {
        $dune = Books::aBook(isbn: '9780441172719');
        $dune->startReading(new DateTimeImmutable('2026-09-01'));
        $this->given($dune);

        LibraryServer::tool(RemoveBookTool::class, ['book_id' => $dune->id()->value])
            ->assertOk()
            ->assertStructuredContent([
                'id' => $dune->id()->value,
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
            ]);

        $this->assertNull($this->app->make(BookQueries::class)->find($dune->id()->value));
    }

    #[Test]
    public function removing_it_again_changes_nothing_and_says_the_book_is_gone(): void
    {
        $dune = Books::aBook();
        $this->given($dune);
        $this->given(Books::aBook('Hypérion', 'Dan Simmons'));

        LibraryServer::tool(RemoveBookTool::class, ['book_id' => $dune->id()->value])->assertOk();
        LibraryServer::tool(RemoveBookTool::class, ['book_id' => $dune->id()->value])
            ->assertHasErrors([sprintf('No book with id "%s" in the library.', $dune->id()->value)]);

        $this->assertSame(1, $this->app->make(BookQueries::class)->search(new BookSearch())->total);
    }

    #[Test]
    public function an_id_the_model_made_up_is_refused(): void
    {
        LibraryServer::tool(RemoveBookTool::class, ['book_id' => 'dune'])->assertHasErrors(['"dune" is not a valid book id: expected a UUID.']);
    }

    #[Test]
    public function a_missing_id_is_refused_with_a_message_the_model_can_fix(): void
    {
        LibraryServer::tool(RemoveBookTool::class, [])->assertHasErrors(['book id']);
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
