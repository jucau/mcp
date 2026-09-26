<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class InvalidRating extends DomainException
{
    public static function outOfRange(int $value, int $min, int $max): self
    {
        return new self(sprintf('A rating goes from %d to %d, %d given.', $min, $max, $value));
    }
}
