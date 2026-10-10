<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Books;

final class RateBookTest extends ApiTestCase
{
    #[Test]
    public function a_book_read_is_rated(): void
    {
        $book = Books::aBook();
        $book->finishReading(new DateTimeImmutable('2026-09-20'));
        $this->given($book);

        $this->postJson('/api/books/' . $book->id()->value . '/rate', ['rating' => 5])
            ->assertOk()
            ->assertJsonPath('data.rating', 5);
    }

    #[Test]
    public function a_book_not_finished_cannot_be_rated(): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/rate', ['rating' => 5])
            ->assertConflict()
            ->assertJsonPath('type', url('problems/book-not-finished'));
    }

    #[Test]
    public function a_rating_out_of_the_scale_is_refused_by_the_domain(): void
    {
        $book = Books::aBook();
        $book->finishReading(new DateTimeImmutable('2026-09-20'));
        $this->given($book);

        $this->postJson('/api/books/' . $book->id()->value . '/rate', ['rating' => 6])
            ->assertUnprocessable()
            ->assertJsonPath('type', url('problems/invalid-rating'))
            ->assertJsonPath('detail', 'A rating goes from 1 to 5, 6 given.');
    }

    /**
     * @param array<string, mixed> $body
     */
    #[Test]
    #[TestWith([[]])]
    #[TestWith([['rating' => '5']])]
    #[TestWith([['rating' => 4.5]])]
    public function a_rating_that_is_not_an_integer_is_refused(array $body): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/rate', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }
}
