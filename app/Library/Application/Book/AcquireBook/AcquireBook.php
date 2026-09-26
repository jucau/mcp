<?php

declare(strict_types=1);

namespace App\Library\Application\Book\AcquireBook;

final readonly class AcquireBook
{
    public function __construct(
        public string $bookId,
    ) {
    }
}
