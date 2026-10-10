<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Tools;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Application\Book\RemoveBook\RemoveBook;
use App\Library\Application\Book\RemoveBook\RemoveBookHandler;
use App\Library\Infrastructure\Mcp\BookPresenter;
use App\Library\Infrastructure\Reader\CurrentReader;
use App\Library\Infrastructure\ValidatedData;
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

#[Name('remove_book')]
#[Title('Remove a book')]
#[Description(<<<'TEXT'
    Remove a book from the user's library for good, with its reading history, rating and dates. Only when
    the user explicitly asks to remove it: this is not how to mark a book as read or abandoned. Refused
    while the book is lent. Returns the removed book as it was, so you can tell the user what went.
    TEXT)]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class RemoveBookTool extends LibraryTool
{
    public function __construct(
        private readonly RemoveBookHandler $removeBook,
        private readonly BookQueries $books,
        private readonly BookPresenter $presenter,
        private readonly CurrentReader $reader,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $this->bookId($schema),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->presenter->schema($schema);
    }

    protected function respond(Request $request): ResponseFactory
    {
        $arguments = ValidatedData::of($request->validate([
            'book_id' => ['required', 'string'],
        ]));

        $bookId = $arguments->string('book_id');
        $book = $this->books->find($this->reader->id(), $bookId);

        $this->removeBook->handle(new RemoveBook($this->reader->id()->value, $bookId));

        Assert::isInstanceOf($book, BookView::class);

        return Response::structured($this->presenter->present($book));
    }
}
