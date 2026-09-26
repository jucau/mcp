<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Application\Book\Query;

use App\Library\Application\Book\Query\BookSearch;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class BookSearchTest extends TestCase
{
    #[Test]
    public function by_default_a_search_reads_the_first_page_of_20_books(): void
    {
        $search = new BookSearch();

        $this->assertSame([1, 20], [$search->page, $search->perPage]);
    }

    #[Test]
    #[TestWith([1, 1])]
    #[TestWith([3, 100])]
    public function a_search_reads_a_page_of_1_to_100_books(int $page, int $perPage): void
    {
        $search = new BookSearch(page: $page, perPage: $perPage);

        $this->assertSame([$page, $perPage], [$search->page, $search->perPage]);
    }

    #[Test]
    #[TestWith([0, 20])]
    #[TestWith([1, 0])]
    #[TestWith([1, 101])]
    public function a_page_out_of_bounds_is_refused(int $page, int $perPage): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BookSearch(page: $page, perPage: $perPage);
    }
}
