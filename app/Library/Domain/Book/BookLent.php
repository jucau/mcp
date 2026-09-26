<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class BookLent extends DomainException
{
    public static function cannotBeRemoved(Title $title, Borrower $borrower): self
    {
        return new self(sprintf('"%s" is lent to %s: get it back before removing it.', $title->value, $borrower->value));
    }
}
