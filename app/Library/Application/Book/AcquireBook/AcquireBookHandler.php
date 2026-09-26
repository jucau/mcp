<?php

declare(strict_types=1);

namespace App\Library\Application\Book\AcquireBook;

use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;

final readonly class AcquireBookHandler
{
    public function __construct(private BookRepository $books)
    {
    }

    public function handle(AcquireBook $command): void
    {
        $book = $this->books->get(BookId::fromString($command->bookId));

        $book->acquire();

        $this->books->save($book);
    }
}
