<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookChangedMeanwhile;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\DuplicateIsbn;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\ReaderId;
use App\Library\Domain\Book\UnknownBook;
use Webmozart\Assert\Assert;

final class InMemoryBookRepository implements BookRepository
{
    /** @var array<string, int> the version of each book as this session last read or wrote it */
    private array $versions = [];

    public function __construct(private readonly StoredBooks $stored = new StoredBooks())
    {
    }

    /**
     * Another repository over the same books, as another request would have: what it writes is a change this one
     * has not seen.
     */
    public function anotherSession(): self
    {
        return new self($this->stored);
    }

    public function save(Book $book): void
    {
        $id = $book->id()->value;

        // Mirrors the optimistic lock on the version column.
        if (($this->stored->rows[$id]['version'] ?? null) !== ($this->versions[$id] ?? null)) {
            throw BookChangedMeanwhile::sinceItWasRead();
        }

        // Mirrors the unique index on (reader_id, isbn) (BOOK-UNIQ): the aggregate alone cannot see the other books.
        $isbn = $book->isbn();

        foreach ($this->stored->rows as $existing) {
            if ($isbn instanceof Isbn
                && $existing['book']->reader()->equals($book->reader())
                && ! $existing['book']->id()->equals($book->id())
                && $existing['book']->isbn()?->value === $isbn->value) {
                throw DuplicateIsbn::isbn($isbn);
            }
        }

        $version = ($this->versions[$id] ?? 0) + 1;
        $this->stored->rows[$id] = ['book' => $this->snapshot($book), 'version' => $version];
        $this->versions[$id] = $version;
    }

    public function get(ReaderId $reader, BookId $id): Book
    {
        $row = $this->stored->rows[$id->value] ?? null;

        if ($row === null || ! $row['book']->reader()->equals($reader)) {
            throw UnknownBook::withId($id);
        }

        $this->versions[$id->value] = $row['version'];

        return $this->snapshot($row['book']);
    }

    public function remove(ReaderId $reader, BookId $id): void
    {
        $row = $this->stored->rows[$id->value] ?? null;
        $version = $this->versions[$id->value] ?? null;

        if ($row !== null && $row['book']->reader()->equals($reader)) {
            if ($version !== null && $version !== $row['version']) {
                throw BookChangedMeanwhile::sinceItWasRead();
            }

            unset($this->stored->rows[$id->value]);
        }

        unset($this->versions[$id->value]);
    }

    private function snapshot(Book $book): Book
    {
        $copy = unserialize(serialize($book));
        Assert::isInstanceOf($copy, Book::class);

        return $copy;
    }
}
