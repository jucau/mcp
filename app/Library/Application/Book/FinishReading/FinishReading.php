<?php

declare(strict_types=1);

namespace App\Library\Application\Book\FinishReading;

use DateTimeImmutable;

final readonly class FinishReading
{
    public function __construct(
        public string $readerId,
        public string $bookId,
        public ?DateTimeImmutable $on = null,
    ) {
    }
}
