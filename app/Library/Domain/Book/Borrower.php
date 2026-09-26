<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

final readonly class Borrower extends Text
{
    protected static function label(): string
    {
        return 'borrower';
    }
}
