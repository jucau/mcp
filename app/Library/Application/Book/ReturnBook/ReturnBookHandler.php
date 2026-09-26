<?php

declare(strict_types=1);

namespace App\Library\Application\Book\ReturnBook;

use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;

final readonly class ReturnBookHandler
{
    public function __construct(private BookRepository $books)
    {
    }

    public function handle(ReturnBook $command): void
    {
        $book = $this->books->get(BookId::fromString($command->bookId));

        $book->markAsReturned();

        $this->books->save($book);
    }
}
