<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Reader;

use App\Library\Domain\Book\ReaderId;

interface CurrentReader
{
    public function id(): ReaderId;

    public function timezone(): string;
}
