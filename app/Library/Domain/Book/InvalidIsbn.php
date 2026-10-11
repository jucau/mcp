<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class InvalidIsbn extends RuntimeException implements Refusal
{
    public static function value(string $value): self
    {
        return new self(sprintf('"%s" is not a valid ISBN-10 or ISBN-13.', $value));
    }
}
