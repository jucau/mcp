<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookSearch;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Book\ReaderId;
use App\Library\Domain\Book\ReadingStatus;
use App\Library\Infrastructure\Auth\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LibrarySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DemoLibrarySeederTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function the_demo_library_of_julien_fills_every_shelf(): void
    {
        $this->seed(LibrarySeeder::class);
        $julien = ReaderId::fromString(LibrarySeeder::JULIEN);

        $this->assertSame(23, $this->booksOn($julien, new BookSearch()));
        $this->assertSame(9, $this->booksOn($julien, new BookSearch(readingStatus: ReadingStatus::Read)));
        $this->assertSame(2, $this->booksOn($julien, new BookSearch(readingStatus: ReadingStatus::Reading)));
        $this->assertSame(2, $this->booksOn($julien, new BookSearch(readingStatus: ReadingStatus::Abandoned)));
        $this->assertSame(10, $this->booksOn($julien, new BookSearch(readingStatus: ReadingStatus::ToRead)));
        $this->assertSame(4, $this->booksOn($julien, new BookSearch(owned: false)));
        $this->assertSame(3, $this->booksOn($julien, new BookSearch(lent: true)));
    }

    #[Test]
    public function camille_has_a_library_of_her_own_that_shares_an_isbn_with_julien(): void
    {
        $this->seed(LibrarySeeder::class);
        $camille = ReaderId::fromString(LibrarySeeder::CAMILLE);
        $dune = new BookSearch(isbn: Isbn::fromString('9780441172719'));

        $this->assertSame(5, $this->booksOn($camille, new BookSearch()));
        $this->assertSame(1, $this->booksOn($camille, $dune));
        $this->assertSame(1, $this->booksOn(ReaderId::fromString(LibrarySeeder::JULIEN), $dune));
        $this->assertSame(1, $this->booksOn($camille, new BookSearch(lent: true)));
    }

    #[Test]
    public function each_demo_account_opens_the_library_of_its_reader(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            [LibrarySeeder::CAMILLE => 'camille@marque-page.test', LibrarySeeder::JULIEN => 'julien@marque-page.test'],
            User::query()->orderBy('email')->pluck('email', 'reader_id')->all(),
        );
        $this->assertTrue(Hash::check(AccountSeeder::PASSWORD, User::query()->where('email', 'julien@marque-page.test')->firstOrFail()->password));
    }

    private function booksOn(ReaderId $reader, BookSearch $shelf): int
    {
        return $this->app->make(BookQueries::class)->search($reader, $shelf)->total;
    }
}
