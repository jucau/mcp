<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class InvalidText extends DomainException
{
    public static function blank(string $label): self
    {
        return new self(sprintf('A %s cannot be blank.', $label));
    }

    public static function tooLong(string $label, int $maxLength): self
    {
        return new self(sprintf('A %s cannot exceed %d characters.', $label, $maxLength));
    }
}
