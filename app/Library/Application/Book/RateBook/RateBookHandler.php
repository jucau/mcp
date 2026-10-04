<?php

declare(strict_types=1);

namespace App\Library\Application\Book\RateBook;

use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Rating;
use App\Library\Domain\Book\ReaderId;

final readonly class RateBookHandler
{
    public function __construct(private BookRepository $books)
    {
    }

    public function handle(RateBook $command): void
    {
        $book = $this->books->get(ReaderId::fromString($command->readerId), BookId::fromString($command->bookId));

        $book->rate(Rating::fromInt($command->rating));

        $this->books->save($book);
    }
}
