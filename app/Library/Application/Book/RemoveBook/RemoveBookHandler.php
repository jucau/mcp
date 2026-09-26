<?php

declare(strict_types=1);

namespace App\Library\Application\Book\RemoveBook;

use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;

final readonly class RemoveBookHandler
{
    public function __construct(private BookRepository $books)
    {
    }

    public function handle(RemoveBook $command): void
    {
        $book = $this->books->get(BookId::fromString($command->bookId));

        $book->ensureRemovable();

        $this->books->remove($book->id());
    }
}
