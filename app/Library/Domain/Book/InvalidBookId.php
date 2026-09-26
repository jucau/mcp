<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class InvalidBookId extends DomainException
{
    public static function value(string $value): self
    {
        return new self(sprintf('"%s" is not a valid book id: expected a UUID.', $value));
    }
}
