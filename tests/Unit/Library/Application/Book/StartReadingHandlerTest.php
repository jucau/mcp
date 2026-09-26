<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\StartReading\StartReading;
use App\Library\Application\Book\StartReading\StartReadingHandler;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\InvalidBookId;
use App\Library\Domain\Book\Rating;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Domain\Book\ReadingTransitionNotAllowed;
use App\Library\Domain\Book\UnknownBook;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Books;
use Tests\Unit\Library\Application\UseCaseTestCase;

final class StartReadingHandlerTest extends UseCaseTestCase
{
    #[Test]
    #[TestWith([ReadingStatus::ToRead])]
    #[TestWith([ReadingStatus::Abandoned])]
    public function a_book_to_read_or_abandoned_is_started_on_the_given_day(ReadingStatus $from): void
    {
        $id = $this->given($this->bookIn($from));

        $this->start($id, self::day('2026-08-01'));

        $this->assertSame(ReadingStatus::Reading, $this->book($id)->readingStatus());
        $this->assertEquals(self::day('2026-08-01'), $this->book($id)->startedOn());
    }

    #[Test]
    #[TestWith([ReadingStatus::Reading])]
    #[TestWith([ReadingStatus::Read])]
    public function a_book_being_read_or_already_read_cannot_be_started(ReadingStatus $from): void
    {
        $id = $this->given($this->bookIn($from));

        $this->expectException(ReadingTransitionNotAllowed::class);

        $this->start($id);
    }

    #[Test]
    public function without_a_day_the_reading_starts_today(): void
    {
        $id = $this->given(Books::aBook());

        $this->start($id);

        $this->assertEquals(self::day(self::TODAY), $this->book($id)->startedOn());
    }

    #[Test]
    public function resuming_an_abandoned_book_forgets_its_end_and_rating(): void
    {
        $book = $this->bookIn(ReadingStatus::Abandoned);
        $book->rate(Rating::fromInt(2));

        $id = $this->given($book);

        $this->start($id);

        $this->assertNull($this->book($id)->finishedOn());
        $this->assertNull($this->book($id)->rating());
    }

    #[Test]
    public function the_book_id_is_read_whatever_its_case(): void
    {
        $id = $this->given(Books::aBook());

        $this->start(strtoupper($id));

        $this->assertSame(ReadingStatus::Reading, $this->book($id)->readingStatus());
    }

    #[Test]
    public function an_unknown_book_cannot_be_started(): void
    {
        $this->expectException(UnknownBook::class);

        $this->start(BookId::generate()->value);
    }

    #[Test]
    public function a_malformed_book_id_is_refused(): void
    {
        $this->expectException(InvalidBookId::class);

        $this->start('dune');
    }

    private function start(string $id, ?DateTimeImmutable $on = null): void
    {
        (new StartReadingHandler($this->books, $this->clock))->handle(new StartReading($id, $on));
    }
}
