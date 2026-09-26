<?php

declare(strict_types=1);

namespace App\Library\Application\Book\Query;

use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\ReadingStatus;
use Webmozart\Assert\Assert;

final readonly class BookSearch
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(
        public ?string $text = null,
        public ?Isbn $isbn = null,
        public ?ReadingStatus $readingStatus = null,
        public ?bool $owned = null,
        public ?bool $lent = null,
        public ?int $finishedIn = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {
        Assert::greaterThanEq($page, 1, 'The page starts at 1, %s given.');
        Assert::range($perPage, 1, self::MAX_PER_PAGE, 'A page holds %2$s to %3$s books, %1$s given.');
    }
}
