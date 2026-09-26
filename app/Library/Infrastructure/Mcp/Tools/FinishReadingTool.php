<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Tools;

use App\Library\Application\Book\FinishReading\FinishReading;
use App\Library\Application\Book\FinishReading\FinishReadingHandler;
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

#[Name('finish_reading')]
#[Title('Finish reading')]
#[Description(<<<'TEXT'
    Record that the user finished reading a book, whether or not they recorded starting it. Refused
    for a book already read or abandoned. Returns the updated book.
    TEXT)]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class FinishReadingTool extends LibraryTool
{
    public function __construct(
        private readonly FinishReadingHandler $finishReading,
        private readonly BookQueries $books,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $this->bookId($schema),
            'on' => $this->day($schema),
        ];
    }

    protected function respond(Request $request): ResponseFactory
    {
        $arguments = new ToolArguments($request->validate([
            'book_id' => ['required', 'string'],
            'on' => ['nullable', 'date_format:Y-m-d'],
        ]));

        $bookId = $arguments->string('book_id');

        $this->finishReading->handle(new FinishReading($bookId, $arguments->dayOrNull('on')));

        $book = $this->books->find($bookId);
        Assert::isInstanceOf($book, BookView::class);

        return Response::structured(BookPresenter::present($book));
    }
}
