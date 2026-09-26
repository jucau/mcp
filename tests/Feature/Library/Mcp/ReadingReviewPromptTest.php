<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Application\Clock;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Rating;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Prompts\ReadingReviewPrompt;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Announced;
use Tests\Support\Library\Books;
use Tests\Support\Library\FrozenClock;
use Tests\Support\Library\PromptMessages;
use Tests\TestCase;

final class ReadingReviewPromptTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2026-09-27')));
    }

    #[Test]
    public function it_announces_its_contract_to_the_user(): void
    {
        $this->assertEquals([
            'name' => 'reading_review',
            'title' => 'Review my reading year',
            'description' => "Review the user's reading over a year: what they finished, what they abandoned, what stands out.",
            'arguments' => [[
                'name' => 'year',
                'description' => 'The year to review, e.g. "2025". Leave it out for the current year.',
                'required' => false,
            ]],
        ], Announced::prompt('reading_review'));
    }

    #[Test]
    public function it_reviews_the_books_finished_or_abandoned_in_the_year(): void
    {
        $read = Books::aBook('Dune', 'Frank Herbert');
        $read->startReading(new DateTimeImmutable('2024-12-20'));
        $read->finishReading(new DateTimeImmutable('2025-01-10'));
        $read->rate(Rating::fromInt(5));
        $this->given($read);
        $abandoned = Books::aBook('Ulysse', 'James Joyce');
        $abandoned->startReading(new DateTimeImmutable('2025-04-01'));
        $abandoned->abandon(new DateTimeImmutable('2025-04-19'));
        $this->given($abandoned);
        $otherYear = Books::aBook('Fondation', 'Isaac Asimov');
        $otherYear->startReading(new DateTimeImmutable('2026-01-02'));
        $otherYear->finishReading(new DateTimeImmutable('2026-01-20'));
        $this->given($otherYear);

        $messages = PromptMessages::of(LibraryServer::prompt(ReadingReviewPrompt::class, ['year' => '2025'])->assertOk());

        $this->assertSame([['role' => 'user', 'text' => <<<TEXT
            Review my reading year 2025: how many books I finished and how many I abandoned, the ones I loved most, and what stands out (authors, themes, the time each book took me). Keep it warm and short. Answer in the language of our conversation.

            The books I finished or abandoned in 2025:
            [{"id":"{$read->id()->value}","title":"Dune","author":"Frank Herbert","isbn":null,"owned":true,"reading_status":"read","started_on":"2024-12-20","finished_on":"2025-01-10","rating":5,"lent_to":null,"lent_on":null},{"id":"{$abandoned->id()->value}","title":"Ulysse","author":"James Joyce","isbn":null,"owned":true,"reading_status":"abandoned","started_on":"2025-04-01","finished_on":"2025-04-19","rating":null,"lent_to":null,"lent_on":null}]
            TEXT]], $messages);
    }

    #[Test]
    public function without_a_year_it_reviews_the_readers_current_year(): void
    {
        $messages = PromptMessages::of(LibraryServer::prompt(ReadingReviewPrompt::class)->assertOk());

        $this->assertStringStartsWith('Review my reading year 2026:', $messages[0]['text']);
    }

    #[Test]
    #[TestWith(['25'])]
    #[TestWith(['last year'])]
    public function a_year_that_is_not_four_digits_is_refused(string $year): void
    {
        LibraryServer::prompt(ReadingReviewPrompt::class, ['year' => $year])
            ->assertHasErrors(['The year field must be 4 digits.']);
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
