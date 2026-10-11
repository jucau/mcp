<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class DuplicateIsbn extends RuntimeException implements Refusal
{
    public static function isbn(Isbn $isbn): self
    {
        return new self(sprintf('A book with ISBN %s is already in the library.', $isbn->value));
    }
}
