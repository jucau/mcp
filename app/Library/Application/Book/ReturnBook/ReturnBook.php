<?php

declare(strict_types=1);

namespace App\Library\Application\Book\ReturnBook;

final readonly class ReturnBook
{
    public function __construct(
        public string $readerId,
        public string $bookId,
    ) {
    }
}
