<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use Ramsey\Uuid\Uuid;

final readonly class BookId
{
    private function __construct(public string $value)
    {
        if (! Uuid::isValid($value)) {
            throw InvalidBookId::value($value);
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
