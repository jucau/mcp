<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

final readonly class Rating
{
    public const int MIN = 1;

    public const int MAX = 5;

    private function __construct(public int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidRating::outOfRange($value, self::MIN, self::MAX);
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }
}
