<?php

declare(strict_types=1);

namespace App\Library\Application\Book\LookUpBook;

final readonly class LookUpBook
{
    public function __construct(public string $isbn)
    {
    }
}
