<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class BookLent extends RuntimeException implements Refusal
{
    public static function cannotBeRemoved(Title $title, Borrower $borrower): self
    {
        return new self(sprintf('"%s" is lent to %s: get it back before removing it.', $title->value, $borrower->value));
    }
}
