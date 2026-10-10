<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\AbandonReading\AbandonReading;
use App\Library\Application\Book\AbandonReading\AbandonReadingHandler;
use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
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

    public function __invoke(Request $request, string $book, CurrentReader $reader): BookResource
    {
        $body = ValidatedData::of($request->validate(['on' => ['nullable', 'date_format:Y-m-d']]));

        $this->handler->handle(new AbandonReading($reader->id()->value, $book, $body->dayOrNull('on')));

        $view = $this->books->find($reader->id(), $book);
        Assert::isInstanceOf($view, BookView::class);

        return new BookResource($view);
    }
}
