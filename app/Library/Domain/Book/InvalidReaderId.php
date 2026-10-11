<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class InvalidReaderId extends RuntimeException implements Refusal
{
    public static function value(string $value): self
    {
        return new self(sprintf('"%s" is not a valid reader id: expected a UUID.', $value));
    }
}
