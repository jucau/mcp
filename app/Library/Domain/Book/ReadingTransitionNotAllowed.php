<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class ReadingTransitionNotAllowed extends RuntimeException implements Refusal
{
    public static function from(Title $title, ReadingStatus $status, string $action): self
    {
        return new self(sprintf('Cannot %s "%s": its reading status is %s.', $action, $title->value, $status->value));
    }
}
