<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Library\Application\Clock;
use App\Library\Infrastructure\SystemClock;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SystemClockTest extends TestCase
{
    #[Test]
    public function today_is_already_the_next_day_for_a_reader_ahead_of_utc(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-09-26 22:30', new DateTimeZone('UTC')));

        $this->assertEquals(new DateTimeImmutable('2026-09-27'), new SystemClock('Europe/Paris')->today());
    }

    #[Test]
    public function today_is_still_the_day_before_for_a_reader_behind_utc(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-09-27 02:00', new DateTimeZone('UTC')));

        $this->assertEquals(new DateTimeImmutable('2026-09-26'), new SystemClock('America/New_York')->today());
    }

    #[Test]
    public function the_library_counts_days_in_the_configured_reader_timezone(): void
    {
        config(['library.reader_timezone' => 'Europe/Paris']);
        $this->travelTo(new DateTimeImmutable('2026-09-26 22:30', new DateTimeZone('UTC')));

        $this->assertEquals(new DateTimeImmutable('2026-09-27'), $this->app->make(Clock::class)->today());
    }
}
