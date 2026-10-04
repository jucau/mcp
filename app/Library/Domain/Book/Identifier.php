<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;
use Ramsey\Uuid\Uuid;

abstract readonly class Identifier
{
    final protected function __construct(public string $value)
    {
        if (! Uuid::isValid($value)) {
            throw static::invalid($value);
        }
    }

    public static function fromString(string $value): static
    {
        return new static(strtolower($value));
    }

    /**
     * @param static $other
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    abstract protected static function invalid(string $value): DomainException;
}
