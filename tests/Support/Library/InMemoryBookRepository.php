<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\DuplicateIsbn;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\ReaderId;
use App\Library\Domain\Book\UnknownBook;
use Webmozart\Assert\Assert;

final class InMemoryBookRepository implements BookRepository
{
    /** @var array<string, Book> */
    private array $books = [];

    public function save(Book $book): void
    {
        // Mirrors the unique index on (reader_id, isbn) (BOOK-UNIQ): the aggregate alone cannot see the other books.
        $isbn = $book->isbn();

        foreach ($this->books as $existing) {
            if ($isbn instanceof Isbn
                && $existing->reader()->equals($book->reader())
                && ! $existing->id()->equals($book->id())
                && $existing->isbn()?->value === $isbn->value) {
                throw DuplicateIsbn::isbn($isbn);
            }
        }

        $this->books[$book->id()->value] = $this->snapshot($book);
    }

    public function get(ReaderId $reader, BookId $id): Book
    {
        $book = $this->books[$id->value] ?? null;

        if (! $book instanceof Book || ! $book->reader()->equals($reader)) {
            throw UnknownBook::withId($id);
        }

        return $this->snapshot($book);
    }

    public function remove(ReaderId $reader, BookId $id): void
    {
        if (($this->books[$id->value] ?? null)?->reader()->equals($reader) === true) {
            unset($this->books[$id->value]);
        }
    }

    private function snapshot(Book $book): Book
    {
        $copy = unserialize(serialize($book));
        Assert::isInstanceOf($copy, Book::class);

        return $copy;
    }
}
