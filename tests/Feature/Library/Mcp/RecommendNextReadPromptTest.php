<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Borrower;
use App\Library\Domain\Book\Rating;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Prompts\RecommendNextReadPrompt;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Announced;
use Tests\Support\Library\Books;
use Tests\Support\Library\PromptMessages;
use Tests\TestCase;

final class RecommendNextReadPromptTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_announces_its_contract_to_the_user(): void
    {
        $this->assertEquals([
            'name' => 'recommend_next_read',
            'title' => 'Recommend my next read',
            'description' => 'Recommend the next book to read among the ones the user has at home and has not started, based on what they read and how they rated it.',
            'arguments' => [[
                'name' => 'mood',
                'description' => 'What the user feels like reading, in their words, e.g. "something short" or "science fiction".',
                'required' => false,
            ]],
        ], Announced::prompt('recommend_next_read'));
    }

    #[Test]
    public function it_asks_for_a_book_of_the_shelf_to_read_given_the_readers_taste_and_mood(): void
    {
        $toRead = Books::aBook('Les Furtifs', 'Alain Damasio');
        $this->given($toRead);
        $lent = Books::aBook('Neuromancien', 'William Gibson');
        $lent->lendTo(Borrower::fromString('Léa'), new DateTimeImmutable('2026-09-01'));
        $this->given($lent);
        $read = Books::aBook('Dune', 'Frank Herbert');
        $read->startReading(new DateTimeImmutable('2025-01-04'));
        $read->finishReading(new DateTimeImmutable('2025-02-10'));
        $read->rate(Rating::fromInt(5));
        $this->given($read);
        $abandoned = Books::aBook('Ulysse', 'James Joyce');
        $abandoned->startReading(new DateTimeImmutable('2026-04-01'));
        $abandoned->abandon(new DateTimeImmutable('2026-04-19'));
        $this->given($abandoned);

        $messages = PromptMessages::of(LibraryServer::prompt(RecommendNextReadPrompt::class, ['mood' => 'something short'])->assertOk());

        $this->assertSame([['role' => 'user', 'text' => <<<TEXT
            Recommend the next book I should read, chosen only among the books I have at home and have not started yet.
            Base your choice on what I read and how I rated it: a rating goes from 1 (disliked) to 5 (loved), and an abandoned book did not hold my interest.

            What I feel like reading: something short

            Name one book and say why in a few sentences, then name a runner-up. If there is nothing to choose from, say so. Answer in the language of our conversation.

            My books to read:
            [{"id":"{$toRead->id()->value}","title":"Les Furtifs","author":"Alain Damasio","isbn":null,"owned":true,"reading_status":"to_read","started_on":null,"finished_on":null,"rating":null,"lent_to":null,"lent_on":null}]

            What I read:
            [{"id":"{$read->id()->value}","title":"Dune","author":"Frank Herbert","isbn":null,"owned":true,"reading_status":"read","started_on":"2025-01-04","finished_on":"2025-02-10","rating":5,"lent_to":null,"lent_on":null}]

            What I abandoned:
            [{"id":"{$abandoned->id()->value}","title":"Ulysse","author":"James Joyce","isbn":null,"owned":true,"reading_status":"abandoned","started_on":"2026-04-01","finished_on":"2026-04-19","rating":null,"lent_to":null,"lent_on":null}]
            TEXT]], $messages);
    }

    #[Test]
    public function without_a_mood_it_does_not_mention_one(): void
    {
        $messages = PromptMessages::of(LibraryServer::prompt(RecommendNextReadPrompt::class)->assertOk());

        $this->assertStringNotContainsString('What I feel like reading', $messages[0]['text']);
        $this->assertStringContainsString("My books to read:\n[]", $messages[0]['text']);
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
