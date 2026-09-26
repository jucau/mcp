<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class DuplicateIsbn extends DomainException
{
    public static function isbn(Isbn $isbn): self
    {
        return new self(sprintf('A book with ISBN %s is already in the library.', $isbn->value));
    }
}
