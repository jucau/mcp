<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class InvalidBookId extends RuntimeException implements Refusal
{
    public static function value(string $value): self
    {
        return new self(sprintf('"%s" is not a valid book id: expected a UUID.', $value));
    }
}
