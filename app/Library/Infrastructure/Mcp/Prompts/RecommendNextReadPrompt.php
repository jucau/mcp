<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Prompts;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Application\Book\Query\Shelf;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Infrastructure\Reader\CurrentReader;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use Webmozart\Assert\Assert;

#[Name('recommend_next_read')]
#[Title('Recommend my next read')]
#[Description('Recommend the next book to read among the ones the user has at home and has not started, based on what they read and how they rated it.')]
final class RecommendNextReadPrompt extends Prompt
{
    public function __construct(
        private readonly BookQueries $books,
        private readonly EmbeddedBooks $embedded,
        private readonly CurrentReader $reader,
    ) {
    }

    public function arguments(): array
    {
        return [
            new Argument('mood', 'What the user feels like reading, in their words, e.g. "something short" or "science fiction".'),
        ];
    }

    public function handle(Request $request): Response
    {
        $mood = $request->validate(['mood' => ['nullable', 'string']])['mood'] ?? null;
        Assert::nullOrString($mood);

        return Response::text(implode("\n\n", array_filter([
            "Recommend the next book I should read, chosen only among the books I have at home and have not started yet.\n"
            . 'Base your choice on what I read and how I rated it: a rating goes from 1 (disliked) to 5 (loved), and an abandoned book did not hold my interest.',
            $mood === null ? null : 'What I feel like reading: ' . $mood,
            'Name one book and say why in a few sentences, then name a runner-up. If there is nothing to choose from, say so. Answer in the language of our conversation.',
            "My books to read:\n" . $this->embedded->json($this->books->all($this->reader->id(), Shelf::ToRead->search())),
            "What I read:\n" . $this->embedded->json($this->books->all($this->reader->id(), new BookSearch(readingStatus: ReadingStatus::Read))),
            "What I abandoned:\n" . $this->embedded->json($this->books->all($this->reader->id(), new BookSearch(readingStatus: ReadingStatus::Abandoned))),
        ])));
    }
}
