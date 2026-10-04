<?php

declare(strict_types=1);

namespace App\Library\Infrastructure;

use App\Library\Application\Clock;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;

final readonly class SystemClock implements Clock
{
    /**
     * @param Closure(): string $readerTimezone
     */
    public function __construct(private Closure $readerTimezone)
    {
    }

    public function today(): DateTimeImmutable
    {
        // The reader's calendar day, but at midnight in the application timezone like every stored date: a
        // midnight in Paris is the day before in UTC, and would compare as earlier than a date read back.
        return new DateTimeImmutable(CarbonImmutable::now(($this->readerTimezone)())->toDateString());
    }
}
