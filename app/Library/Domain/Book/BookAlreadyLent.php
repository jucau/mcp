<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class BookAlreadyLent extends RuntimeException implements Refusal
{
    public static function to(Title $title, Borrower $borrower): self
    {
        return new self(sprintf('"%s" is already lent to %s.', $title->value, $borrower->value));
    }
}
