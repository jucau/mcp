<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Eloquent\Repositories;

use App\Library\Domain\Book\Author;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookChangedMeanwhile;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Borrower;
use App\Library\Domain\Book\DuplicateIsbn;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\Loan;
use App\Library\Domain\Book\Rating;
use App\Library\Domain\Book\ReaderId;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Domain\Book\Title;
use App\Library\Domain\Book\UnknownBook;
use App\Library\Infrastructure\Persistence\Eloquent\Models\BookRecord;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentBookRepository implements BookRepository
{
    private const string ISBN_INDEX = 'books_reader_id_isbn_unique';

    /**
     * The version of each book as this repository last read or wrote it: the optimistic lock compares it with the
     * one in the database, so the aggregate never has to carry a number that means nothing to the domain.
     *
     * @var array<string, int>
     */
    private array $versions = [];

    public function get(ReaderId $reader, BookId $id): Book
    {
        $record = BookRecord::query()
            ->where('reader_id', $reader->value)
            ->where('uuid', $id->value)
            ->first() ?? throw UnknownBook::withId($id);

        $this->versions[$id->value] = $record->version;

        return Book::reconstitute(
            BookId::fromString($record->uuid),
            ReaderId::fromString($record->reader_id),
            Title::fromString($record->title),
            Author::fromString($record->author),
            $record->isbn === null ? null : Isbn::fromString($record->isbn),
            $record->owned,
            ReadingStatus::from($record->reading_status),
            $this->toDay($record->started_on),
            $this->toDay($record->finished_on),
            $record->rating === null ? null : Rating::fromInt($record->rating),
            $record->loan_borrower === null || $record->lent_on === null
                ? null
                : new Loan(Borrower::fromString($record->loan_borrower), $this->toDay($record->lent_on)),
        );
    }

    public function save(Book $book): void
    {
        $uuid = $book->id()->value;
        $version = $this->versions[$uuid] ?? null;

        $this->versions[$uuid] = $version === null ? $this->insert($book) : $this->update($book, $version);
    }

    public function remove(ReaderId $reader, BookId $id): void
    {
        $book = BookRecord::query()->where('reader_id', $reader->value)->where('uuid', $id->value);
        $version = $this->versions[$id->value] ?? null;

        $removed = $version === null ? $book->delete() : (clone $book)->where('version', $version)->delete();

        if ($removed === 0 && $book->exists()) {
            throw BookChangedMeanwhile::sinceItWasRead();
        }

        unset($this->versions[$id->value]);
    }

    private function insert(Book $book): int
    {
        $record = new BookRecord();
        $record->forceFill(['uuid' => $book->id()->value, 'version' => 1, ...$this->stateOf($book)]);

        try {
            $record->save();
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            $isbn = $book->isbn();

            // The only other unique index is the one on the uuid: someone else saved this very book first.
            throw $isbn instanceof Isbn && str_contains($uniqueConstraintViolationException->getMessage(), self::ISBN_INDEX)
                ? DuplicateIsbn::isbn($isbn)
                : BookChangedMeanwhile::sinceItWasRead();
        }

        return 1;
    }

    private function update(Book $book, int $version): int
    {
        $updated = BookRecord::query()
            ->where('uuid', $book->id()->value)
            ->where('version', $version)
            ->update(['version' => $version + 1, ...$this->stateOf($book)]);

        if ($updated === 0) {
            throw BookChangedMeanwhile::sinceItWasRead();
        }

        return $version + 1;
    }

    /**
     * @return array<string, bool|int|string|CarbonImmutable|null>
     */
    private function stateOf(Book $book): array
    {
        return [
            'reader_id' => $book->reader()->value,
            'title' => $book->title()->value,
            'author' => $book->author()->value,
            'isbn' => $book->isbn()?->value,
            'owned' => $book->isOwned(),
            'reading_status' => $book->readingStatus()->value,
            'started_on' => $this->toCarbon($book->startedOn()),
            'finished_on' => $this->toCarbon($book->finishedOn()),
            'rating' => $book->rating()?->value,
            'loan_borrower' => $book->loan()?->borrower->value,
            'lent_on' => $this->toCarbon($book->loan()?->lentOn),
        ];
    }

    private function toCarbon(?DateTimeImmutable $day): ?CarbonImmutable
    {
        return $day instanceof DateTimeImmutable ? CarbonImmutable::instance($day) : null;
    }

    /**
     * @return ($day is null ? null : DateTimeImmutable)
     */
    private function toDay(?DateTimeInterface $day): ?DateTimeImmutable
    {
        return $day instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($day) : null;
    }
}
