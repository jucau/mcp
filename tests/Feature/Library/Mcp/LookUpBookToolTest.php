<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Catalog\CatalogEntry;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Tools\LookUpBookTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Announced;
use Tests\Support\Library\InMemoryBookCatalog;
use Tests\Support\Library\OutputSchema;
use Tests\TestCase;

final class LookUpBookToolTest extends TestCase
{
    private InMemoryBookCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new InMemoryBookCatalog();
        $this->app->instance(BookCatalog::class, $this->catalog);
    }

    #[Test]
    public function it_announces_its_contract_to_the_model(): void
    {
        $this->assertEquals([
            'name' => 'lookup_book',
            'title' => 'Look up a book by ISBN',
            'description' => <<<'TEXT'
                Look up the title and the author of a book from its ISBN, in an open catalog outside the user's library
                (Open Library). Use it before add_book when the user gives an ISBN without a title or an author. It
                changes nothing in the library, and says nothing of whether the user has the book: search_books does.
                When the catalog does not know the ISBN or cannot be reached, ask the user for the title and the author.
                TEXT,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'isbn' => [
                        'type' => 'string',
                        'description' => 'ISBN-10 or ISBN-13, hyphens allowed, as the user gave it: never invent one.',
                    ],
                ],
                'required' => ['isbn'],
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'isbn' => ['type' => 'string', 'description' => 'The ISBN-13 that was looked up.'],
                    'title' => ['type' => 'string'],
                    'author' => [
                        'type' => ['string', 'null'],
                        'description' => 'Every author in one text, as add_book expects it. null when the catalog names none: ask the user.',
                    ],
                    'published_in' => ['type' => ['integer', 'null'], 'description' => 'The year this edition was published.'],
                    'pages' => ['type' => ['integer', 'null']],
                ],
                'required' => ['isbn', 'title', 'author', 'published_in', 'pages'],
            ],
            'annotations' => [
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ], Announced::tool('lookup_book'));
    }

    #[Test]
    public function it_returns_what_the_catalog_knows_of_the_book(): void
    {
        $this->catalog->knows(new CatalogEntry('9780441172719', 'Dune', 'Frank Herbert', 1965, 896));

        $response = LibraryServer::tool(LookUpBookTool::class, ['isbn' => '0-441-17271-7'])
            ->assertOk()
            ->assertStructuredContent([
                'isbn' => '9780441172719',
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'published_in' => 1965,
                'pages' => 896,
            ]);

        OutputSchema::assertRespected($response, 'lookup_book');
    }

    #[Test]
    public function an_entry_with_nothing_but_a_title_still_respects_the_announced_schema(): void
    {
        $this->catalog->knows(new CatalogEntry('9780441172719', 'Dune'));

        $response = LibraryServer::tool(LookUpBookTool::class, ['isbn' => '9780441172719'])
            ->assertOk()
            ->assertStructuredContent(['isbn' => '9780441172719', 'title' => 'Dune', 'author' => null, 'published_in' => null, 'pages' => null]);

        OutputSchema::assertRespected($response, 'lookup_book');
    }

    #[Test]
    public function an_isbn_the_catalog_does_not_know_reaches_the_model_even_in_production(): void
    {
        config(['app.debug' => false]);

        LibraryServer::tool(LookUpBookTool::class, ['isbn' => '9780441172719'])
            ->assertHasErrors(['The open catalog knows no book with ISBN 9780441172719.']);
    }

    #[Test]
    public function a_catalog_that_is_down_reaches_the_model_even_in_production(): void
    {
        config(['app.debug' => false]);
        $this->catalog->goDown();

        LibraryServer::tool(LookUpBookTool::class, ['isbn' => '9780441172719'])
            ->assertHasErrors(['The open catalog cannot be reached right now.']);
    }

    #[Test]
    public function an_invalid_isbn_is_refused_by_the_domain_without_asking_the_catalog(): void
    {
        LibraryServer::tool(LookUpBookTool::class, ['isbn' => 'dune'])
            ->assertHasErrors(['"dune" is not a valid ISBN-10 or ISBN-13.']);

        $this->assertSame([], $this->catalog->asked());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[Test]
    #[TestWith([[]])]
    #[TestWith([['isbn' => 9780441172719]])]
    public function malformed_arguments_are_refused_with_a_message_the_model_can_fix(array $arguments): void
    {
        LibraryServer::tool(LookUpBookTool::class, $arguments)->assertHasErrors(['isbn']);
    }
}
