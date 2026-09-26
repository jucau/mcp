<?php

declare(strict_types=1);

namespace App\Library\Infrastructure;

use App\Library\Application\Clock;
use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }
}
