<?php

declare(strict_types=1);

namespace App\Library\Application\Book\RemoveBook;

final readonly class RemoveBook
{
    public function __construct(
        public string $readerId,
        public string $bookId,
    ) {
    }
}
