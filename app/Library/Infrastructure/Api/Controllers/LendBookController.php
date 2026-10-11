<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\LendBook\LendBook;
use App\Library\Application\Book\LendBook\LendBookHandler;
use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Domain\Book\BookAlreadyLent;
use App\Library\Domain\Book\BookChangedMeanwhile;
use App\Library\Domain\Book\BookNotOwned;
use App\Library\Domain\Book\InvalidBookId;
use App\Library\Domain\Book\InvalidText;
use App\Library\Domain\Book\UnknownBook;
use App\Library\Infrastructure\Api\Resources\BookResource;
use App\Library\Infrastructure\Reader\CurrentReader;
use App\Library\Infrastructure\ValidatedData;
use Illuminate\Http\Request;
use Webmozart\Assert\Assert;

final readonly class LendBookController
{
    public function __construct(private LendBookHandler $handler, private BookQueries $books)
    {
    }

    /**
     * @throws UnknownBook
     * @throws InvalidBookId
     * @throws InvalidText
     * @throws BookNotOwned
     * @throws BookAlreadyLent
     * @throws BookChangedMeanwhile
     */
    public function __invoke(Request $request, string $book, CurrentReader $reader): BookResource
    {
        $body = ValidatedData::of($request->validate(['borrower' => ['required', 'string'], 'on' => ['nullable', 'date_format:Y-m-d']]));

        $this->handler->handle(new LendBook($reader->id()->value, $book, $body->string('borrower'), $body->dayOrNull('on')));

        $view = $this->books->find($reader->id(), $book);
        Assert::isInstanceOf($view, BookView::class);

        return new BookResource($view);
    }
}
