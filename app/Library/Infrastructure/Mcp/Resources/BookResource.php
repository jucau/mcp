<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\UnknownBook;
use App\Library\Infrastructure\Mcp\BookPresenter;
use App\Library\Infrastructure\Mcp\CurrentReader;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Support\UriTemplate;
use Webmozart\Assert\Assert;

#[Name('book')]
#[Title('A book')]
#[Description("One book of the user's library, with everything the library knows about it: reading, rating, loan.")]
final class BookResource extends LibraryResource implements HasUriTemplate
{
    public function __construct(
        private readonly BookQueries $books,
        private readonly BookPresenter $presenter,
        private readonly CurrentReader $reader,
    ) {
    }

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('library://books/{id}');
    }

    protected function read(Request $request): array
    {
        $id = $request->get('id');
        Assert::string($id);

        $book = $this->books->find($this->reader->id(), $id) ?? throw UnknownBook::withId(BookId::fromString($id));

        return $this->presenter->present($book);
    }
}
