<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Application\Clock;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Tools\LendBookTool;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\AnnouncedTools;
use Tests\Support\Library\Books;
use Tests\Support\Library\FrozenClock;
use Tests\TestCase;

final class LendBookToolTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2026-09-27')));
    }

    #[Test]
    public function it_announces_its_contract_to_the_model(): void
    {
        $this->assertEquals([
            'name' => 'lend_book',
            'title' => 'Lend a book',
            'description' => <<<'TEXT'
                Record that the user lent one of their books to someone. Refused for a book on the wishlist or
                already lent. Returns the updated book.
                TEXT,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'book_id' => [
                        'type' => 'string',
                        'format' => 'uuid',
                        'description' => 'The id of the book, as returned by search_books.',
                    ],
                    'borrower' => [
                        'type' => 'string',
                        'description' => 'Who borrowed it, named as the user did, e.g. "Marie".',
                    ],
                    'on' => [
                        'type' => 'string',
                        'format' => 'date',
                        'description' => 'The day it happened (YYYY-MM-DD), only if the user said. Leave it out for today: never guess a date.',
                    ],
                ],
                'required' => ['book_id', 'borrower'],
            ],
            'annotations' => [
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => false,
            ],
        ], AnnouncedTools::named('lend_book'));
    }

    #[Test]
    public function it_lends_today_unless_told_otherwise_and_returns_the_updated_book(): void
    {
        $book = Books::aBook();
        $this->given($book);

        LibraryServer::tool(LendBookTool::class, ['book_id' => $book->id()->value, 'borrower' => ' Marie '])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
                ->where('id', $book->id()->value)
                ->where('lent_to', 'Marie')
                ->where('lent_on', '2026-09-27')
                ->etc());
    }

    #[Test]
    public function it_lends_on_the_day_the_user_gave(): void
    {
        $book = Books::aBook();
        $this->given($book);

        LibraryServer::tool(LendBookTool::class, ['book_id' => $book->id()->value, 'borrower' => 'Marie', 'on' => '2026-09-15'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->where('lent_on', '2026-09-15')->etc());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[Test]
    #[TestWith([['borrower' => 'Marie'], 'book id'])]
    #[TestWith([['book_id' => '0df5fc16-18df-4e11-bc4c-578b7791de7d'], 'borrower'])]
    #[TestWith([['book_id' => '0df5fc16-18df-4e11-bc4c-578b7791de7d', 'borrower' => '   '], 'borrower field is required'])]
    #[TestWith([['book_id' => '0df5fc16-18df-4e11-bc4c-578b7791de7d', 'borrower' => 'Marie', 'on' => '2026-02-30'], 'on field'])]
    public function malformed_arguments_are_refused_with_a_message_the_model_can_fix(array $arguments, string $field): void
    {
        LibraryServer::tool(LendBookTool::class, $arguments)->assertHasErrors([$field]);
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
