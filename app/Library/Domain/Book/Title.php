<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

final readonly class Title extends Text
{
    protected static function label(): string
    {
        return 'title';
    }
}
