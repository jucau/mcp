<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class UnknownBook extends DomainException
{
    public static function withId(BookId $id): self
    {
        return new self(sprintf('No book with id "%s" in the library.', $id->value));
    }
}
