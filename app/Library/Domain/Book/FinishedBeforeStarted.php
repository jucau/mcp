<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Refusal;
use DateTimeImmutable;
use RuntimeException;

final class FinishedBeforeStarted extends RuntimeException implements Refusal
{
    public static function on(Title $title, DateTimeImmutable $finishedOn, DateTimeImmutable $startedOn): self
    {
        return new self(sprintf(
            '"%s" cannot end on %s, before it was started on %s.',
            $title->value,
            $finishedOn->format('Y-m-d'),
            $startedOn->format('Y-m-d'),
        ));
    }
}
