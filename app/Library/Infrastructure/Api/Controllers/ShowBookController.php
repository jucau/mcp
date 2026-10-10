<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\InvalidBookId;
use App\Library\Domain\Book\UnknownBook;
use App\Library\Infrastructure\Api\Resources\BookResource;
use App\Library\Infrastructure\Reader\CurrentReader;

final readonly class ShowBookController
{
    public function __construct(private BookQueries $books)
    {
    }

    /**
     * @throws UnknownBook
     * @throws InvalidBookId
     */
    public function __invoke(string $book, CurrentReader $reader): BookResource
    {
        return new BookResource($this->books->find($reader->id(), $book) ?? throw UnknownBook::withId(BookId::fromString($book)));
    }
}
