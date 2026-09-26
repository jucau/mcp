<?php

declare(strict_types=1);

namespace App\Library\Application\Book\Query;

final readonly class BookView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $author,
        public ?string $isbn,
        public bool $owned,
        public string $readingStatus,
        public ?string $startedOn,
        public ?string $finishedOn,
        public ?int $rating,
        public ?string $borrower,
        public ?string $lentOn,
    ) {
    }
}
