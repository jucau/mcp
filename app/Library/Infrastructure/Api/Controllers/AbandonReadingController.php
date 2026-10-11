<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\AbandonReading\AbandonReading;
use App\Library\Application\Book\AbandonReading\AbandonReadingHandler;
use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Domain\Book\BookChangedMeanwhile;
use App\Library\Domain\Book\FinishedBeforeStarted;
use App\Library\Domain\Book\InvalidBookId;
use App\Library\Domain\Book\ReadingTransitionNotAllowed;
use App\Library\Domain\Book\UnknownBook;
use App\Library\Infrastructure\Api\Resources\BookResource;
use App\Library\Infrastructure\Reader\CurrentReader;
use App\Library\Infrastructure\ValidatedData;
use Illuminate\Http\Request;
use Webmozart\Assert\Assert;

final readonly class AbandonReadingController
{
    public function __construct(private AbandonReadingHandler $handler, private BookQueries $books)
    {
    }

    /**
     * @throws UnknownBook
     * @throws InvalidBookId
     * @throws ReadingTransitionNotAllowed
     * @throws FinishedBeforeStarted
     * @throws BookChangedMeanwhile
     */
    public function __invoke(Request $request, string $book, CurrentReader $reader): BookResource
    {
        $body = ValidatedData::of($request->validate(['on' => ['nullable', 'date_format:Y-m-d']]));

        $this->handler->handle(new AbandonReading($reader->id()->value, $book, $body->dayOrNull('on')));

        $view = $this->books->find($reader->id(), $book);
        Assert::isInstanceOf($view, BookView::class);

        return new BookResource($view);
    }
}
