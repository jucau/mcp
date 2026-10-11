<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use RuntimeException;

final class BookChangedMeanwhile extends RuntimeException implements Refusal
{
    public static function sinceItWasRead(): self
    {
        return new self('This book changed in the meantime: read it again before acting on it.');
    }
}
