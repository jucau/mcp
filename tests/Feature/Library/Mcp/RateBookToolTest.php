<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Rating;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Tools\RateBookTool;
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

final class RateBookToolTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_announces_its_contract_to_the_model(): void
    {
        $this->assertEquals([
            'name' => 'rate_book',
            'title' => 'Rate a book',
            'description' => <<<'TEXT'
                Record the rating the user gives a book, once they have read or abandoned it. Rating it again
                replaces the previous rating. Only a rating the user stated: never deduce one from their opinion.
                Returns the updated book.
                TEXT,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'book_id' => [
                        'type' => 'string',
                        'format' => 'uuid',
                        'description' => 'The id of the book, as returned by search_books.',
                    ],
                    'rating' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 5,
                        'description' => 'From 1 (disliked) to 5 (loved).',
                    ],
                ],
                'required' => ['book_id', 'rating'],
            ],
            'outputSchema' => BookSchema::BOOK,
            'annotations' => [
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => false,
            ],
        ], Announced::tool('rate_book'));
    }

    #[Test]
    public function it_replaces_the_rating_and_returns_the_updated_book(): void
    {
        $book = $this->aReadBook();
        $book->rate(Rating::fromInt(3));
        $this->given($book);

        $response = LibraryServer::tool(RateBookTool::class, ['book_id' => $book->id()->value, 'rating' => 4])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
                ->where('id', $book->id()->value)
                ->where('rating', 4)
                ->etc());

        OutputSchema::assertRespected($response, 'rate_book');
    }

    #[Test]
    public function a_rating_out_of_range_is_refused_by_the_domain(): void
    {
        $book = $this->aReadBook();
        $this->given($book);

        LibraryServer::tool(RateBookTool::class, ['book_id' => $book->id()->value, 'rating' => 6])
            ->assertHasErrors(['A rating goes from 1 to 5, 6 given.']);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[Test]
    #[TestWith([['rating' => 4], 'book id'])]
    #[TestWith([['book_id' => '0df5fc16-18df-4e11-bc4c-578b7791de7d'], 'rating'])]
    #[TestWith([['book_id' => '0df5fc16-18df-4e11-bc4c-578b7791de7d', 'rating' => '4'], 'rating'])]
    #[TestWith([['book_id' => '0df5fc16-18df-4e11-bc4c-578b7791de7d', 'rating' => 4.5], 'rating'])]
    public function malformed_arguments_are_refused_with_a_message_the_model_can_fix(array $arguments, string $field): void
    {
        LibraryServer::tool(RateBookTool::class, $arguments)->assertHasErrors([$field]);
    }

    private function aReadBook(): Book
    {
        $book = Books::aBook();
        $book->startReading(new DateTimeImmutable('2026-09-01'));
        $book->finishReading(new DateTimeImmutable('2026-09-20'));

        return $book;
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
