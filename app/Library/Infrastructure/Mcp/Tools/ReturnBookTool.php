<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Tools;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Application\Book\ReturnBook\ReturnBook;
use App\Library\Application\Book\ReturnBook\ReturnBookHandler;
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

#[Name('return_book')]
#[Title('Return a book')]
#[Description(<<<'TEXT'
    Record that a lent book came back to the user. The loan is forgotten: who borrowed it and when is
    not kept. Refused if the book is not lent. Returns the updated book.
    TEXT)]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class ReturnBookTool extends LibraryTool
{
    public function __construct(
        private readonly ReturnBookHandler $returnBook,
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

        $this->returnBook->handle(new ReturnBook($this->reader->id()->value, $bookId));

        $book = $this->books->find($this->reader->id(), $bookId);
        Assert::isInstanceOf($book, BookView::class);

        return Response::structured($this->presenter->present($book));
    }
}
