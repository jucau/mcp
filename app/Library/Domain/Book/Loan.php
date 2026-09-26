<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DateTimeImmutable;

final readonly class Loan
{
    public function __construct(
        public Borrower $borrower,
        public DateTimeImmutable $lentOn,
    ) {
    }
}
