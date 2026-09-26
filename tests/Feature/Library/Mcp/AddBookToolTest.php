<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Tools\AddBookTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Books;
use Tests\TestCase;

final class AddBookToolTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_adds_an_owned_book_and_returns_it_with_its_id(): void
    {
        LibraryServer::tool(AddBookTool::class, ['title' => ' Dune ', 'author' => 'Frank Herbert', 'isbn' => '0-441-17271-7'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $book): AssertableJson => $book
                ->whereType('id', 'string')
                ->where('title', 'Dune')
                ->where('author', 'Frank Herbert')
                ->where('isbn', '9780441172719')
                ->where('owned', true)
                ->where('reading_status', 'to_read')
                ->where('started_on', null)
                ->where('finished_on', null)
                ->where('rating', null)
                ->where('lent_to', null)
                ->where('lent_on', null));
    }

    #[Test]
    public function it_adds_a_book_to_the_wishlist(): void
    {
        LibraryServer::tool(AddBookTool::class, ['title' => 'Hypérion', 'author' => 'Dan Simmons', 'owned' => false])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $book): AssertableJson => $book->where('title', 'Hypérion')->where('owned', false)->etc());
    }

    #[Test]
    public function a_business_refusal_reaches_the_model_even_in_production(): void
    {
        config(['app.debug' => false]);
        $this->app->make(BookRepository::class)->save(Books::aBook(isbn: '9780441172719'));

        LibraryServer::tool(AddBookTool::class, ['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '0441172717'])
            ->assertHasErrors(['A book with ISBN 9780441172719 is already in the library.']);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[Test]
    #[TestWith([['author' => 'Frank Herbert'], 'title'])]
    #[TestWith([['title' => 'Dune'], 'author'])]
    #[TestWith([['title' => 'Dune', 'author' => 'Frank Herbert', 'owned' => 'no'], 'owned'])]
    public function malformed_arguments_are_refused_with_a_message_the_model_can_fix(array $arguments, string $field): void
    {
        LibraryServer::tool(AddBookTool::class, $arguments)->assertHasErrors([$field]);
    }
}
