<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Books;

final class ReadingTest extends ApiTestCase
{
    #[Test]
    public function a_reading_starts_today_unless_told_otherwise(): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/start-reading')
            ->assertOk()
            ->assertJsonPath('data.reading_status', 'reading')
            ->assertJsonPath('data.started_on', '2026-09-27');
    }

    #[Test]
    public function a_reading_starts_on_the_day_given(): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/start-reading', ['on' => '2026-09-15'])
            ->assertOk()
            ->assertJsonPath('data.started_on', '2026-09-15');
    }

    #[Test]
    public function a_reading_finishes_on_the_day_given(): void
    {
        $book = Books::aBook();
        $book->startReading(new DateTimeImmutable('2026-09-01'));
        $this->given($book);

        $this->postJson('/api/books/' . $book->id()->value . '/finish-reading', ['on' => '2026-09-20'])
            ->assertOk()
            ->assertJsonPath('data.reading_status', 'read')
            ->assertJsonPath('data.finished_on', '2026-09-20');
    }

    #[Test]
    public function a_reading_is_abandoned(): void
    {
        $book = Books::aBook();
        $book->startReading(new DateTimeImmutable('2026-09-01'));
        $this->given($book);

        $this->postJson('/api/books/' . $book->id()->value . '/abandon-reading')
            ->assertOk()
            ->assertJsonPath('data.reading_status', 'abandoned')
            ->assertJsonPath('data.finished_on', '2026-09-27');
    }

    #[Test]
    public function a_reading_is_abandoned_on_the_day_given(): void
    {
        $book = Books::aBook();
        $book->startReading(new DateTimeImmutable('2026-09-01'));
        $this->given($book);

        $this->postJson('/api/books/' . $book->id()->value . '/abandon-reading', ['on' => '2026-09-15'])
            ->assertOk()
            ->assertJsonPath('data.finished_on', '2026-09-15');
    }

    #[Test]
    public function a_book_not_being_read_cannot_be_abandoned(): void
    {
        $book = $this->given(Books::aBook('Dune'));

        $this->postJson('/api/books/' . $book->id()->value . '/abandon-reading')
            ->assertConflict()
            ->assertJsonPath('type', url('problems/reading-transition-not-allowed'))
            ->assertJsonPath('detail', 'Cannot abandon "Dune": its reading status is to_read.');
    }

    #[Test]
    public function a_reading_cannot_end_before_it_started(): void
    {
        $book = Books::aBook();
        $book->startReading(new DateTimeImmutable('2026-09-10'));
        $this->given($book);

        $this->postJson('/api/books/' . $book->id()->value . '/finish-reading', ['on' => '2026-09-01'])
            ->assertUnprocessable()
            ->assertJsonPath('type', url('problems/finished-before-started'));
    }

    #[Test]
    #[TestWith(['2026-02-30'])]
    #[TestWith(['27/09/2026'])]
    #[TestWith([20260927])]
    public function a_day_that_is_not_a_date_is_refused(mixed $on): void
    {
        $book = $this->given(Books::aBook());

        $this->postJson('/api/books/' . $book->id()->value . '/start-reading', ['on' => $on])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['on']);
    }

    #[Test]
    public function an_action_on_a_book_the_reader_does_not_have_is_not_found(): void
    {
        $this->postJson('/api/books/0df5fc16-18df-4e11-bc4c-578b7791de7d/start-reading')
            ->assertNotFound()
            ->assertJsonPath('type', url('problems/unknown-book'));
    }
}
