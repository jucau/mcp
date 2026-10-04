<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\Borrower;
use App\Library\Domain\Book\ReaderId;
use App\Library\Domain\Book\ReadingStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Library\Books;
use Tests\Support\Library\FrozenClock;
use Tests\Support\Library\InMemoryBookRepository;
use Tests\Support\Library\Readers;

abstract class UseCaseTestCase extends TestCase
{
    protected const string TODAY = '2026-09-26';

    protected const string STARTED_ON = '2026-01-01';

    protected const string ENDED_ON = '2026-02-01';

    protected InMemoryBookRepository $books;

    protected FrozenClock $clock;

    protected function setUp(): void
    {
        $this->books = new InMemoryBookRepository();
        $this->clock = new FrozenClock(new DateTimeImmutable(self::TODAY));
    }

    protected function given(Book $book): string
    {
        $this->books->save($book);

        return $book->id()->value;
    }

    protected function book(string $id): Book
    {
        return $this->books->get(Readers::me(), BookId::fromString($id));
    }

    protected function bookIn(ReadingStatus $status, ?ReaderId $reader = null): Book
    {
        $book = Books::aBook(reader: $reader);

        if ($status === ReadingStatus::ToRead) {
            return $book;
        }

        $book->startReading(self::day(self::STARTED_ON));

        if ($status === ReadingStatus::Read) {
            $book->finishReading(self::day(self::ENDED_ON));
        }

        if ($status === ReadingStatus::Abandoned) {
            $book->abandon(self::day(self::ENDED_ON));
        }

        return $book;
    }

    protected function lentTo(string $borrower, ?ReaderId $reader = null): Book
    {
        $book = Books::aBook(reader: $reader);
        $book->lendTo(Borrower::fromString($borrower), self::day('2026-05-02'));

        return $book;
    }

    protected static function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }
}
