<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Tools;

use App\Library\Application\Book\AddBook\AddBook;
use App\Library\Application\Book\AddBook\AddBookHandler;
use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
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

#[Name('add_book')]
#[Title('Add a book')]
#[Description(<<<'TEXT'
    Add a book to the user's library, to be read. Set owned to false for a book they only wish to read (their
    wishlist). Returns the new book with its id. Refused if a book with the same ISBN is already there: search
    for it instead of adding it twice.
    TEXT)]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
final class AddBookTool extends LibraryTool
{
    public function __construct(
        private readonly AddBookHandler $addBook,
        private readonly BookQueries $books,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'author' => $schema->string()->required()
                ->description('Several authors fit in one text, e.g. "Terry Pratchett & Neil Gaiman".'),
            'isbn' => $schema->string()
                ->description('ISBN-10 or ISBN-13, hyphens allowed. Leave it out when unknown: never invent one.'),
            'owned' => $schema->boolean()->default(true)
                ->description('false for a book the user does not own yet (wishlist).'),
        ];
    }

    protected function respond(Request $request): ResponseFactory
    {
        $arguments = new ToolArguments($request->validate([
            'title' => ['required', 'string'],
            'author' => ['required', 'string'],
            'isbn' => ['nullable', 'string'],
            'owned' => ['nullable', 'boolean:strict'],
        ]));

        $id = $this->addBook->handle(new AddBook(
            $arguments->string('title'),
            $arguments->string('author'),
            $arguments->stringOrNull('isbn'),
            $arguments->boolOrNull('owned') ?? true,
        ));

        $book = $this->books->find($id->value);
        Assert::isInstanceOf($book, BookView::class);

        return Response::structured(BookPresenter::present($book));
    }
}
