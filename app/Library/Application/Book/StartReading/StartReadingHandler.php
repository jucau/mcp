<?php

declare(strict_types=1);

namespace App\Library\Application\Book\StartReading;

use App\Library\Application\Clock;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;

final readonly class StartReadingHandler
{
    public function __construct(
        private BookRepository $books,
        private Clock $clock,
    ) {
    }

    public function handle(StartReading $command): void
    {
        $book = $this->books->get(BookId::fromString($command->bookId));

        $book->startReading($command->on ?? $this->clock->today());

        $this->books->save($book);
    }
}
