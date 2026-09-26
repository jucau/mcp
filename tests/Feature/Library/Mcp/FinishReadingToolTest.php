<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Application\Clock;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Tools\FinishReadingTool;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Announced;
use Tests\Support\Library\Books;
use Tests\Support\Library\BookSchema;
use Tests\Support\Library\FrozenClock;
use Tests\Support\Library\OutputSchema;
use Tests\TestCase;

final class FinishReadingToolTest extends TestCase
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
            'name' => 'finish_reading',
            'title' => 'Finish reading',
            'description' => <<<'TEXT'
                Record that the user finished reading a book, whether or not they recorded starting it. Refused
                for a book already read or abandoned. Returns the updated book.
                TEXT,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'book_id' => [
                        'type' => 'string',
                        'format' => 'uuid',
                        'description' => 'The id of the book, as returned by search_books.',
                    ],
                    'on' => [
                        'type' => 'string',
                        'format' => 'date',
                        'description' => 'The day it happened (YYYY-MM-DD), only if the user said. Leave it out for today: never guess a date.',
                    ],
                ],
                'required' => ['book_id'],
            ],
            'outputSchema' => BookSchema::BOOK,
            'annotations' => [
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => false,
            ],
        ], Announced::tool('finish_reading'));
    }

    #[Test]
    public function it_happens_today_unless_told_otherwise_and_returns_the_updated_book(): void
    {
        $book = Books::aBook();
        $book->startReading(new DateTimeImmutable('2026-09-01'));
        $this->given($book);

        $response = LibraryServer::tool(FinishReadingTool::class, ['book_id' => $book->id()->value])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
                ->where('id', $book->id()->value)
                ->where('reading_status', 'read')
                ->where('started_on', '2026-09-01')
                ->where('finished_on', '2026-09-27')
                ->etc());

        OutputSchema::assertRespected($response, 'finish_reading');
    }

    #[Test]
    public function it_happens_on_the_day_the_user_gave(): void
    {
        $book = Books::aBook();
        $this->given($book);

        LibraryServer::tool(FinishReadingTool::class, ['book_id' => $book->id()->value, 'on' => '2026-09-15'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->where('finished_on', '2026-09-15')->etc());
    }

    #[Test]
    public function a_missing_id_is_refused_with_a_message_the_model_can_fix(): void
    {
        LibraryServer::tool(FinishReadingTool::class, [])->assertHasErrors(['book id']);
    }

    #[Test]
    #[TestWith(['27/09/2026'])]
    #[TestWith(['2026-02-30'])]
    public function a_malformed_date_is_refused_with_a_message_the_model_can_fix(string $on): void
    {
        $book = Books::aBook();
        $this->given($book);

        LibraryServer::tool(FinishReadingTool::class, ['book_id' => $book->id()->value, 'on' => $on])->assertHasErrors(['on field']);
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
