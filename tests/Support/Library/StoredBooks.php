<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Domain\Book\Book;

final class StoredBooks
{
    /** @var array<string, array{book: Book, version: int}> */
    public array $rows = [];
}
