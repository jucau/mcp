<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

enum ReadingStatus: string
{
    case ToRead = 'to_read';
    case Reading = 'reading';
    case Read = 'read';
    case Abandoned = 'abandoned';
}
