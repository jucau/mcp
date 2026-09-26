<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class InvalidIsbn extends DomainException
{
    public static function value(string $value): self
    {
        return new self(sprintf('"%s" is not a valid ISBN-10 or ISBN-13.', $value));
    }
}
