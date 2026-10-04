<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book;

use App\Library\Application\Book\RateBook\RateBook;
use App\Library\Application\Book\RateBook\RateBookHandler;
use App\Library\Domain\Book\BookNotFinished;
use App\Library\Domain\Book\InvalidRating;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Domain\Book\UnknownBook;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Tests\Unit\Library\Application\UseCaseTestCase;

final class RateBookHandlerTest extends UseCaseTestCase
{
    #[Test]
    #[TestWith([1])]
    #[TestWith([5])]
    public function a_read_book_is_rated_from_1_to_5(int $rating): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Read));

        $this->rate($id, $rating);

        $this->assertSame($rating, $this->book($id)->rating()?->value);
    }

    #[Test]
    public function an_abandoned_book_is_rated_and_rerated(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Abandoned));

        $this->rate($id, 3);
        $this->rate($id, 1);

        $this->assertSame(1, $this->book($id)->rating()?->value);
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([6])]
    public function a_rating_out_of_range_is_refused(int $rating): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Read));

        $this->expectException(InvalidRating::class);

        $this->rate($id, $rating);
    }

    #[Test]
    public function a_book_not_yet_finished_is_not_rated(): void
    {
        $id = $this->given(Books::aBook());

        try {
            $this->rate($id, 4);
            $this->fail('An unread book was rated.');
        } catch (BookNotFinished) {
            $this->assertNull($this->book($id)->rating());
        }
    }

    #[Test]
    public function the_book_of_another_reader_cannot_be_rated(): void
    {
        $id = $this->given($this->bookIn(ReadingStatus::Read, Readers::someoneElse()));

        $this->expectException(UnknownBook::class);

        $this->rate($id, 4);
    }

    private function rate(string $id, int $rating): void
    {
        (new RateBookHandler($this->books))->handle(new RateBook(Readers::ME, $id, $rating));
    }
}
