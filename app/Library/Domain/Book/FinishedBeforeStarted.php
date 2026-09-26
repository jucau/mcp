<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use DateTimeImmutable;
use DomainException;

final class FinishedBeforeStarted extends DomainException
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
