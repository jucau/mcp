<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Tools;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Infrastructure\Mcp\BookPresenter;
use App\Library\Infrastructure\Mcp\ToolArguments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_books')]
#[Title('Search books')]
#[Description(<<<'TEXT'
    Search the user's library. Every criterion is optional and they combine: with none, it lists every book.
    Use it to find a book's id before acting on it, or to answer questions such as "what am I reading?",
    "what did I lend?" or "which books are on my wishlist?". Results are sorted by title and paginated.
    TEXT)]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class SearchBooksTool extends LibraryTool
{
    public function __construct(private readonly BookQueries $books)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Words to look for in the title or the author. Every word must match; case and accents are ignored.'),
            'isbn' => $schema->string()
                ->description('The exact ISBN of the book, ISBN-10 or ISBN-13, hyphens allowed.'),
            'reading_status' => $schema->string()
                ->enum(array_column(ReadingStatus::cases(), 'value'))
                ->description('Only books at this stage: to_read, reading, read or abandoned.'),
            'owned' => $schema->boolean()
                ->description('true: only books the user owns. false: only their wishlist.'),
            'lent' => $schema->boolean()
                ->description('true: only books currently lent to someone. false: only books at home.'),
            'page' => $schema->integer()->min(1)->default(1),
            'per_page' => $schema->integer()->min(1)->max(BookSearch::MAX_PER_PAGE)->default(20),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'total' => $schema->integer()->required()->description('How many books match, across all pages.'),
            'page' => $schema->integer()->required(),
            'per_page' => $schema->integer()->required(),
            'books' => $schema->array()->items($schema->object(BookPresenter::schema($schema)))->required(),
        ];
    }

    protected function respond(Request $request): ResponseFactory
    {
        $arguments = new ToolArguments($request->validate([
            'query' => ['nullable', 'string'],
            'isbn' => ['nullable', 'string'],
            'reading_status' => ['nullable', Rule::enum(ReadingStatus::class)],
            'owned' => ['nullable', 'boolean:strict'],
            'lent' => ['nullable', 'boolean:strict'],
            'page' => ['nullable', 'integer:strict', 'min:1'],
            'per_page' => ['nullable', 'integer:strict', 'between:1,' . BookSearch::MAX_PER_PAGE],
        ]));

        $isbn = $arguments->stringOrNull('isbn');
        $readingStatus = $arguments->stringOrNull('reading_status');

        $page = $this->books->search(new BookSearch(
            text: $arguments->stringOrNull('query'),
            isbn: $isbn === null ? null : Isbn::fromString($isbn),
            readingStatus: $readingStatus === null ? null : ReadingStatus::from($readingStatus),
            owned: $arguments->boolOrNull('owned'),
            lent: $arguments->boolOrNull('lent'),
            page: $arguments->intOr('page', 1),
            perPage: $arguments->intOr('per_page', 20),
        ));

        return Response::structured([
            'total' => $page->total,
            'page' => $page->page,
            'per_page' => $page->perPage,
            'books' => array_map(BookPresenter::present(...), $page->items),
        ]);
    }
}
