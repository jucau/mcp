<?php

declare(strict_types=1);

namespace App\Library\Application\Book\AddBook;

final readonly class AddBook
{
    public function __construct(
        public string $title,
        public string $author,
        public ?string $isbn = null,
        public bool $owned = true,
    ) {
    }
}
