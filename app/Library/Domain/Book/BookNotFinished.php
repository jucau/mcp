<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class BookNotFinished extends RuntimeException implements Refusal
{
    public static function cannotBeRated(Title $title): self
    {
        return new self(sprintf('"%s" can only be rated once read or abandoned.', $title->value));
    }
}
