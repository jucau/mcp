<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Application\Clock;
use DateTimeImmutable;

final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $today)
    {
    }

    public function today(): DateTimeImmutable
    {
        return $this->today;
    }
}
