<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Application\Book\RateBook\RateBook;
use App\Library\Application\Book\RateBook\RateBookHandler;
use App\Library\Infrastructure\Api\Resources\BookResource;
use App\Library\Infrastructure\Reader\CurrentReader;
use App\Library\Infrastructure\ValidatedData;
use Illuminate\Http\Request;
use Webmozart\Assert\Assert;

final readonly class RateBookController
{
    public function __construct(private RateBookHandler $handler, private BookQueries $books)
    {
    }

    public function __invoke(Request $request, string $book, CurrentReader $reader): BookResource
    {
        $body = ValidatedData::of($request->validate(['rating' => ['required', 'integer:strict']]));

        $this->handler->handle(new RateBook($reader->id()->value, $book, $body->int('rating')));

        $view = $this->books->find($reader->id(), $book);
        Assert::isInstanceOf($view, BookView::class);

        return new BookResource($view);
    }
}
