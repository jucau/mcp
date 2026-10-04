<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

final readonly class ReaderId extends Identifier
{
    protected static function invalid(string $value): InvalidReaderId
    {
        return InvalidReaderId::value($value);
    }
}
