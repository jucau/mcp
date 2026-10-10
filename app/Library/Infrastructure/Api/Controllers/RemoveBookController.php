<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\Controllers;

use App\Library\Application\Book\RemoveBook\RemoveBook;
use App\Library\Application\Book\RemoveBook\RemoveBookHandler;
use App\Library\Domain\Book\BookLent;
use App\Library\Domain\Book\InvalidBookId;
use App\Library\Domain\Book\UnknownBook;
use App\Library\Infrastructure\Reader\CurrentReader;
use Illuminate\Http\Response;

final readonly class RemoveBookController
{
    public function __construct(private RemoveBookHandler $removeBook)
    {
    }

    /**
     * @throws UnknownBook
     * @throws InvalidBookId
     * @throws BookLent
     */
    public function __invoke(string $book, CurrentReader $reader): Response
    {
        $this->removeBook->handle(new RemoveBook($reader->id()->value, $book));

        return response()->noContent();
    }
}
