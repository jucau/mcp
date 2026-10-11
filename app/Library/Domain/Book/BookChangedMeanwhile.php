<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DomainException;

final class BookChangedMeanwhile extends DomainException
{
    public static function sinceItWasRead(): self
    {
        return new self('This book changed in the meantime: read it again before acting on it.');
    }
}
