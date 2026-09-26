<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class BookNotFinished extends DomainException
{
    public static function cannotBeRated(Title $title): self
    {
        return new self(sprintf('"%s" can only be rated once read or abandoned.', $title->value));
    }
}
