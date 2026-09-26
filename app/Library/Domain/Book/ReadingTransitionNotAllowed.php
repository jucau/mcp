<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class ReadingTransitionNotAllowed extends DomainException
{
    public static function from(Title $title, ReadingStatus $status, string $action): self
    {
        return new self(sprintf('Cannot %s "%s": its reading status is %s.', $action, $title->value, $status->value));
    }
}
