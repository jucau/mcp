<?php

declare(strict_types=1);

namespace App\Library\Application\Book\AbandonReading;

use DateTimeImmutable;

final readonly class AbandonReading
{
    public function __construct(
        public string $bookId,
        public ?DateTimeImmutable $on = null,
    ) {
    }
}
