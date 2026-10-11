<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

/**
 * The proof that a book may be removed: the repository removes nothing else, so no use case can remove a book
 * without the aggregate having agreed.
 */
final readonly class Removal
{
    private function __construct(public ReaderId $reader, public BookId $book)
    {
    }

    /**
     * @throws BookLent
     */
    public static function of(Book $book): self
    {
        $book->ensureRemovable();

        return new self($book->reader(), $book->id());
    }
}
