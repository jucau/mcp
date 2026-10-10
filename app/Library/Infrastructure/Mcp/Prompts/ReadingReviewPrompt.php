<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Prompts;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Application\Clock;
use App\Library\Infrastructure\Reader\CurrentReader;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Contracts\Completable;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use Webmozart\Assert\Assert;

#[Name('reading_review')]
#[Title('Review my reading year')]
#[Description("Review the user's reading over a year: what they finished, what they abandoned, what stands out.")]
final class ReadingReviewPrompt extends Prompt implements Completable
{
    public function __construct(
        private readonly BookQueries $books,
        private readonly Clock $clock,
        private readonly EmbeddedBooks $embedded,
        private readonly CurrentReader $reader,
    ) {
    }

    public function arguments(): array
    {
        return [
            new Argument('year', 'The year to review, e.g. "2025". Leave it out for the current year.'),
        ];
    }

    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        if ($argument !== 'year') {
            return CompletionResponse::empty();
        }

        return CompletionResponse::match(array_map(strval(...), $this->books->yearsWithFinishedBooks($this->reader->id())));
    }

    public function handle(Request $request): Response
    {
        $year = $request->validate(['year' => ['nullable', 'string', 'digits:4']])['year'] ?? $this->clock->today()->format('Y');
        Assert::string($year);

        return Response::text(implode("\n\n", [
            sprintf('Review my reading year %s: how many books I finished and how many I abandoned, the ones I loved most, and what stands out (authors, themes, the time each book took me). Keep it warm and short. Answer in the language of our conversation.', $year),
            sprintf("The books I finished or abandoned in %s:\n", $year) . $this->embedded->json($this->books->all($this->reader->id(), new BookSearch(finishedIn: (int) $year))),
        ]));
    }
}
