<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Tools;

use App\Library\Application\Book\LookUpBook\LookUpBook;
use App\Library\Application\Book\LookUpBook\LookUpBookHandler;
use App\Library\Infrastructure\Mcp\CatalogEntryPresenter;
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

#[Name('lookup_book')]
#[Title('Look up a book by ISBN')]
#[Description(<<<'TEXT'
    Look up the title and the author of a book from its ISBN, in an open catalog outside the user's library
    (Open Library). Use it before add_book when the user gives an ISBN without a title or an author. It
    changes nothing in the library, and says nothing of whether the user has the book: search_books does.
    When the catalog does not know the ISBN or cannot be reached, ask the user for the title and the author.
    TEXT)]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld]
final class LookUpBookTool extends LibraryTool
{
    public function __construct(
        private readonly LookUpBookHandler $lookUpBook,
        private readonly CatalogEntryPresenter $presenter,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'isbn' => $schema->string()->required()
                ->description('ISBN-10 or ISBN-13, hyphens allowed, as the user gave it: never invent one.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->presenter->schema($schema);
    }

    protected function respond(Request $request): ResponseFactory
    {
        $arguments = ValidatedData::of($request->validate([
            'isbn' => ['required', 'string'],
        ]));

        return Response::structured($this->presenter->present(
            $this->lookUpBook->handle(new LookUpBook($arguments->string('isbn'))),
        ));
    }
}
