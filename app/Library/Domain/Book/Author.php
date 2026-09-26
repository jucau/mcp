<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

final readonly class Author extends Text
{
    protected static function label(): string
    {
        return 'author';
    }
}
