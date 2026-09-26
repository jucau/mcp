<?php

declare(strict_types=1);

namespace App\Library\Application\Book\StartReading;

use DateTimeImmutable;

final readonly class StartReading
{
    public function __construct(
        public string $bookId,
        public ?DateTimeImmutable $on = null,
    ) {
    }
}
