<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Tools;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Application\Book\RateBook\RateBook;
use App\Library\Application\Book\RateBook\RateBookHandler;
use App\Library\Domain\Book\Rating;
use App\Library\Infrastructure\Mcp\BookPresenter;
use App\Library\Infrastructure\Mcp\ToolArguments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
use Webmozart\Assert\Assert;

#[Name('rate_book')]
#[Title('Rate a book')]
#[Description(<<<'TEXT'
    Record the rating the user gives a book, once they have read or abandoned it. Rating it again
    replaces the previous rating. Only a rating the user stated: never deduce one from their opinion.
    Returns the updated book.
    TEXT)]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class RateBookTool extends LibraryTool
{
    public function __construct(
        private readonly RateBookHandler $rateBook,
        private readonly BookQueries $books,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $this->bookId($schema),
            'rating' => $schema->integer()->min(Rating::MIN)->max(Rating::MAX)->required()
                ->description('From 1 (disliked) to 5 (loved).'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return BookPresenter::schema($schema);
    }

    protected function respond(Request $request): ResponseFactory
    {
        $arguments = new ToolArguments($request->validate([
            'book_id' => ['required', 'string'],
            'rating' => ['required', 'integer:strict'],
        ]));

        $bookId = $arguments->string('book_id');

        $this->rateBook->handle(new RateBook($bookId, $arguments->int('rating')));

        $book = $this->books->find($bookId);
        Assert::isInstanceOf($book, BookView::class);

        return Response::structured(BookPresenter::present($book));
    }
}
