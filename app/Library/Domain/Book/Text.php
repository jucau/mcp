<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

abstract readonly class Text
{
    public const int MAX_LENGTH = 255;

    final private function __construct(public string $value)
    {
        if ($value === '') {
            throw InvalidText::blank(static::label());
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidText::tooLong(static::label(), self::MAX_LENGTH);
        }
    }

    public static function fromString(string $value): static
    {
        return new static(trim($value));
    }

    abstract protected static function label(): string;
}
