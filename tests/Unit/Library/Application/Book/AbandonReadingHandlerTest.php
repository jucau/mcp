<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\AbandonReading\AbandonReading;
use App\Library\Application\Book\AbandonReading\AbandonReadingHandler;
use App\Library\Domain\Book\FinishedBeforeStarted;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Domain\Book\ReadingTransitionNotAllowed;
use App\Library\Domain\Book\UnknownBook;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Readers;
use Tests\Unit\Library\Application\UseCaseTestCase;

final class AbandonReadingHandlerTest extends UseCaseTestCase
{
    #[Test]
    public function a_book_being_read_is_abandoned_on_the_given_day(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Reading));

        $this->abandon($id, self::day('2026-03-10'));

        $this->assertSame(ReadingStatus::Abandoned, $this->book($id)->readingStatus());
        $this->assertEquals(self::day('2026-03-10'), $this->book($id)->finishedOn());
    }

    #[Test]
    #[TestWith([ReadingStatus::ToRead])]
    #[TestWith([ReadingStatus::Read])]
    #[TestWith([ReadingStatus::Abandoned])]
    public function only_a_book_being_read_can_be_abandoned(ReadingStatus $from): void
    {
        $id = $this->given($this->bookIn($from));

        $this->expectException(ReadingTransitionNotAllowed::class);

        $this->abandon($id);
    }

    #[Test]
    public function without_a_day_the_reading_is_abandoned_today(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Reading));

        $this->abandon($id);

        $this->assertEquals(self::day(self::TODAY), $this->book($id)->finishedOn());
    }

    #[Test]
    public function a_book_cannot_be_abandoned_before_it_was_started(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Reading));

        $this->expectException(FinishedBeforeStarted::class);

        $this->abandon($id, self::day('2025-12-31'));
    }

    #[Test]
    public function the_book_of_another_reader_cannot_be_abandoned(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Reading, Readers::someoneElse()));

        $this->expectException(UnknownBook::class);

        $this->abandon($id);
    }

    private function abandon(string $id, ?DateTimeImmutable $on = null): void
    {
        (new AbandonReadingHandler($this->books, $this->clock))->handle(new AbandonReading(Readers::ME, $id, $on));
    }
}
