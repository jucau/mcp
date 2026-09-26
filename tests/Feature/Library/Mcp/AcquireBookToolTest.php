<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Tools\AcquireBookTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\AnnouncedTools;
use Tests\Support\Library\Books;
use Tests\TestCase;

final class AcquireBookToolTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_announces_its_contract_to_the_model(): void
    {
        $this->assertEquals([
            'name' => 'acquire_book',
            'title' => 'Acquire a book',
            'description' => <<<'TEXT'
                Record that the user now owns a book from their wishlist: bought, received, found. A book already
                owned stays as it is. For a book not in the library yet, use add_book instead. Returns the
                updated book.
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
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => false,
            ],
        ], AnnouncedTools::named('acquire_book'));
    }

    #[Test]
    public function a_wished_book_becomes_owned_and_is_returned(): void
    {
        $book = Books::aBook(owned: false);
        $this->given($book);

        LibraryServer::tool(AcquireBookTool::class, ['book_id' => $book->id()->value])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
                ->where('id', $book->id()->value)
                ->where('owned', true)
                ->etc());
    }

    #[Test]
    public function acquiring_it_again_succeeds_and_changes_nothing(): void
    {
        $book = Books::aBook();
        $this->given($book);

        LibraryServer::tool(AcquireBookTool::class, ['book_id' => $book->id()->value])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->where('owned', true)->etc());
    }

    #[Test]
    public function a_missing_id_is_refused_with_a_message_the_model_can_fix(): void
    {
        LibraryServer::tool(AcquireBookTool::class, [])->assertHasErrors(['book id']);
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
