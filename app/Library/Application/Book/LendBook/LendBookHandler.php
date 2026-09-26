<?php

declare(strict_types=1);

namespace App\Library\Application\Book\LendBook;

use App\Library\Application\Clock;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Borrower;

final readonly class LendBookHandler
{
    public function __construct(
        private BookRepository $books,
        private Clock $clock,
    ) {
    }

    public function handle(LendBook $command): void
    {
        $book = $this->books->get(BookId::fromString($command->bookId));

        $book->lendTo(Borrower::fromString($command->borrower), $command->on ?? $this->clock->today());

        $this->books->save($book);
    }
}
