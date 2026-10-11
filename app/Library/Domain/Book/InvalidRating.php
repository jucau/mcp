<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class InvalidRating extends RuntimeException implements Refusal
{
    public static function outOfRange(int $value, int $min, int $max): self
    {
        return new self(sprintf('A rating goes from %d to %d, %d given.', $min, $max, $value));
    }
}
