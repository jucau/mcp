<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class BookAlreadyLent extends DomainException
{
    public static function to(Title $title, Borrower $borrower): self
    {
        return new self(sprintf('"%s" is already lent to %s.', $title->value, $borrower->value));
    }
}
