<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use App\Library\Application\Book\ReturnBook\ReturnBook;
use App\Library\Application\Book\ReturnBook\ReturnBookHandler;
use App\Library\Infrastructure\Api\Resources\BookResource;
use App\Library\Infrastructure\Reader\CurrentReader;
use Webmozart\Assert\Assert;

final readonly class ReturnBookController
{
    public function __construct(private ReturnBookHandler $handler, private BookQueries $books)
    {
    }

    public function __invoke(string $book, CurrentReader $reader): BookResource
    {
        $this->handler->handle(new ReturnBook($reader->id()->value, $book));

        $view = $this->books->find($reader->id(), $book);
        Assert::isInstanceOf($view, BookView::class);

        return new BookResource($view);
    }
}
