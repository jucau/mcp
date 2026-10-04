<?php

declare(strict_types=1);

namespace App\Library\Application\Book\LendBook;

use DateTimeImmutable;

final readonly class LendBook
{
    public function __construct(
        public string $readerId,
        public string $bookId,
        public string $borrower,
        public ?DateTimeImmutable $on = null,
    ) {
    }
}
