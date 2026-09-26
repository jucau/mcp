<?php

declare(strict_types=1);

namespace App\Library\Application\Book\RateBook;

final readonly class RateBook
{
    public function __construct(
        public string $bookId,
        public int $rating,
    ) {
    }
}
