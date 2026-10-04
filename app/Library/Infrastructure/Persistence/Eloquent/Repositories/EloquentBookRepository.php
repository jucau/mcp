<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Eloquent\Repositories;

use App\Library\Domain\Book\Author;
use App\Library\Domain\Book\Book;
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
    public function save(Book $book): void
    {
        $record = BookRecord::query()->where('uuid', $book->id()->value)->first() ?? new BookRecord();
        $record->uuid = $book->id()->value;
        $record->reader_id = $book->reader()->value;
        $record->title = $book->title()->value;
        $record->author = $book->author()->value;
        $record->isbn = $book->isbn()?->value;
        $record->owned = $book->isOwned();
        $record->reading_status = $book->readingStatus()->value;
        $record->started_on = $this->toCarbon($book->startedOn());
        $record->finished_on = $this->toCarbon($book->finishedOn());
        $record->rating = $book->rating()?->value;
        $record->loan_borrower = $book->loan()?->borrower->value;
        $record->lent_on = $this->toCarbon($book->loan()?->lentOn);

        try {
            $record->save();
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            $isbn = $book->isbn();

            throw $isbn instanceof Isbn ? DuplicateIsbn::isbn($isbn) : $uniqueConstraintViolationException;
        }
    }

    public function get(ReaderId $reader, BookId $id): Book
    {
        $record = BookRecord::query()
            ->where('reader_id', $reader->value)
            ->where('uuid', $id->value)
            ->first() ?? throw UnknownBook::withId($id);

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

    public function remove(ReaderId $reader, BookId $id): void
    {
        BookRecord::query()->where('reader_id', $reader->value)->where('uuid', $id->value)->delete();
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
