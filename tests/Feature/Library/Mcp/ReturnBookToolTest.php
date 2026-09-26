<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Borrower;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Tools\ReturnBookTool;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Announced;
use Tests\Support\Library\Books;
use Tests\Support\Library\BookSchema;
use Tests\Support\Library\OutputSchema;
use Tests\TestCase;

final class ReturnBookToolTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_announces_its_contract_to_the_model(): void
    {
        $this->assertEquals([
            'name' => 'return_book',
            'title' => 'Return a book',
            'description' => <<<'TEXT'
                Record that a lent book came back to the user. The loan is forgotten: who borrowed it and when is
                not kept. Refused if the book is not lent. Returns the updated book.
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
            'outputSchema' => BookSchema::BOOK,
            'annotations' => [
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => false,
            ],
        ], Announced::tool('return_book'));
    }

    #[Test]
    public function it_takes_the_book_back_and_returns_it_at_home(): void
    {
        $book = Books::aBook();
        $book->lendTo(Borrower::fromString('Marie'), new DateTimeImmutable('2026-09-01'));
        $this->given($book);

        $response = LibraryServer::tool(ReturnBookTool::class, ['book_id' => $book->id()->value])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
                ->where('id', $book->id()->value)
                ->where('lent_to', null)
                ->where('lent_on', null)
                ->etc());

        OutputSchema::assertRespected($response, 'return_book');
    }

    #[Test]
    public function a_missing_id_is_refused_with_a_message_the_model_can_fix(): void
    {
        LibraryServer::tool(ReturnBookTool::class, [])->assertHasErrors(['book id']);
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
