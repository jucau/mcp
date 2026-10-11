<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class UnknownBook extends RuntimeException implements Refusal
{
    public static function withId(BookId $id): self
    {
        return new self(sprintf('No book with id "%s" in the library.', $id->value));
    }
}
