<?php

declare(strict_types=1);

namespace App\Library\Application\Book\RemoveBook;

use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\ReaderId;
use App\Library\Domain\Book\Removal;

final readonly class RemoveBookHandler
{
    public function __construct(private BookRepository $books)
    {
    }

    public function handle(RemoveBook $command): void
    {
        $book = $this->books->get(ReaderId::fromString($command->readerId), BookId::fromString($command->bookId));

        $this->books->remove(Removal::of($book));
    }
}
