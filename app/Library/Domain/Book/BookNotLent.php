<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class BookNotLent extends DomainException
{
    public static function cannotBeReturned(Title $title): self
    {
        return new self(sprintf('"%s" is not lent: there is nothing to return.', $title->value));
    }
}
