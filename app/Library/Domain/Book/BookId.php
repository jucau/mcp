<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use Ramsey\Uuid\Uuid;

final readonly class BookId extends Identifier
{
    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    protected static function invalid(string $value): InvalidBookId
    {
        return InvalidBookId::value($value);
    }
}
