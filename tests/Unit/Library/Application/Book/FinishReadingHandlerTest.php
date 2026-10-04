<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\FinishReading\FinishReading;
use App\Library\Application\Book\FinishReading\FinishReadingHandler;
use App\Library\Domain\Book\FinishedBeforeStarted;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Domain\Book\ReadingTransitionNotAllowed;
use App\Library\Domain\Book\UnknownBook;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Readers;
use Tests\Unit\Library\Application\UseCaseTestCase;

final class FinishReadingHandlerTest extends UseCaseTestCase
{
    #[Test]
    public function a_book_being_read_is_finished_on_the_given_day(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Reading));

        $this->finish($id, self::day('2026-08-20'));

        $this->assertSame(ReadingStatus::Read, $this->book($id)->readingStatus());
        $this->assertEquals(self::day(self::STARTED_ON), $this->book($id)->startedOn());
        $this->assertEquals(self::day('2026-08-20'), $this->book($id)->finishedOn());
    }

    #[Test]
    public function a_book_read_before_it_was_recorded_has_no_start_day(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::ToRead));

        $this->finish($id, self::day('2019-08-15'));

        $this->assertSame(ReadingStatus::Read, $this->book($id)->readingStatus());
        $this->assertNull($this->book($id)->startedOn());
        $this->assertEquals(self::day('2019-08-15'), $this->book($id)->finishedOn());
    }

    #[Test]
    #[TestWith([ReadingStatus::Read])]
    #[TestWith([ReadingStatus::Abandoned])]
    public function a_book_already_read_or_abandoned_cannot_be_finished(ReadingStatus $from): void
    {
        $id = $this->given($this->bookIn($from));

        $this->expectException(ReadingTransitionNotAllowed::class);

        $this->finish($id);
    }

    #[Test]
    public function without_a_day_the_reading_ends_today(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Reading));

        $this->finish($id);

        $this->assertEquals(self::day(self::TODAY), $this->book($id)->finishedOn());
    }

    #[Test]
    public function a_book_can_be_finished_the_day_it_was_started(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Reading));

        $this->finish($id, self::day(self::STARTED_ON));

        $this->assertSame(ReadingStatus::Read, $this->book($id)->readingStatus());
    }

    #[Test]
    public function a_book_cannot_be_finished_before_it_was_started(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Reading));

        $this->expectException(FinishedBeforeStarted::class);

        $this->finish($id, self::day('2025-12-31'));
    }

    #[Test]
    public function the_book_of_another_reader_cannot_be_finished(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Reading, Readers::someoneElse()));

        $this->expectException(UnknownBook::class);

        $this->finish($id);
    }

    private function finish(string $id, ?DateTimeImmutable $on = null): void
    {
        (new FinishReadingHandler($this->books, $this->clock))->handle(new FinishReading(Readers::ME, $id, $on));
    }
}
