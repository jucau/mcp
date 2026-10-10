<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Tools;

use App\Library\Application\Book\LendBook\LendBook;
use App\Library\Application\Book\LendBook\LendBookHandler;
use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
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

#[Name('lend_book')]
#[Title('Lend a book')]
#[Description(<<<'TEXT'
    Record that the user lent one of their books to someone. Refused for a book on the wishlist or
    already lent. Returns the updated book.
    TEXT)]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class LendBookTool extends LibraryTool
{
    public function __construct(
        private readonly LendBookHandler $lendBook,
        private readonly BookQueries $books,
        private readonly BookPresenter $presenter,
        private readonly CurrentReader $reader,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $this->bookId($schema),
            'borrower' => $schema->string()->required()
                ->description('Who borrowed it, named as the user did, e.g. "Marie".'),
            'on' => $this->day($schema),
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
            'borrower' => ['required', 'string'],
            'on' => ['nullable', 'date_format:Y-m-d'],
        ]));

        $bookId = $arguments->string('book_id');

        $this->lendBook->handle(new LendBook($this->reader->id()->value, $bookId, $arguments->string('borrower'), $arguments->dayOrNull('on')));

        $book = $this->books->find($this->reader->id(), $bookId);
        Assert::isInstanceOf($book, BookView::class);

        return Response::structured($this->presenter->present($book));
    }
}
