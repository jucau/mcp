<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Borrower;
use App\Library\Infrastructure\Auth\ProtectedResource;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Tests\Support\Library\Tokens;
use Tests\TestCase;

final class ShowShelfTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Tokens::actingAs(Accounts::of(Readers::ME), ProtectedResource::Api);
    }

    /**
     * @param list<string> $titles
     */
    #[Test]
    #[TestWith(['to-read', ['Dune']])]
    #[TestWith(['reading', ['Fondation']])]
    #[TestWith(['lent', ['Neuromancien']])]
    #[TestWith(['wishlist', ['Hypérion']])]
    public function each_shelf_holds_only_its_books_whole(string $shelf, array $titles): void
    {
        $this->given(Books::aBook('Dune', 'Frank Herbert'));
        $reading = Books::aBook('Fondation', 'Isaac Asimov');
        $reading->startReading(new DateTimeImmutable('2026-09-01'));
        $this->given($reading);
        $lent = Books::aBook('Neuromancien', 'William Gibson');
        $lent->lendTo(Borrower::fromString('Léa'), new DateTimeImmutable('2026-09-01'));
        $this->given($lent);
        $this->given(Books::aBook('Hypérion', 'Dan Simmons', owned: false));
        $this->given(Books::aBook('Ulysse', 'James Joyce', reader: Readers::someoneElse()));

        $response = $this->getJson('/api/shelves/' . $shelf)->assertOk()->assertJsonMissingPath('meta');

        $this->assertSame($titles, $response->collect('data')->pluck('title')->all());
    }

    #[Test]
    public function a_shelf_the_library_does_not_have_is_not_found(): void
    {
        $this->getJson('/api/shelves/favourites')->assertNotFound();
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
