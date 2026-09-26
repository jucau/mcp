<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Domain\Book\ReadingStatus;
use Database\Seeders\LibrarySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DemoLibrarySeederTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function the_demo_library_fills_every_shelf(): void
    {
        $this->seed(LibrarySeeder::class);

        $this->assertSame(23, $this->booksOn(new BookSearch()));
        $this->assertSame(9, $this->booksOn(new BookSearch(readingStatus: ReadingStatus::Read)));
        $this->assertSame(2, $this->booksOn(new BookSearch(readingStatus: ReadingStatus::Reading)));
        $this->assertSame(2, $this->booksOn(new BookSearch(readingStatus: ReadingStatus::Abandoned)));
        $this->assertSame(10, $this->booksOn(new BookSearch(readingStatus: ReadingStatus::ToRead)));
        $this->assertSame(4, $this->booksOn(new BookSearch(owned: false)));
        $this->assertSame(3, $this->booksOn(new BookSearch(lent: true)));
    }

    private function booksOn(BookSearch $shelf): int
    {
        return $this->app->make(BookQueries::class)->search($shelf)->total;
    }
}
