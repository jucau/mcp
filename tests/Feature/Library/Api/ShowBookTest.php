<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Auth\ProtectedResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Tests\Support\Library\Tokens;
use Tests\TestCase;

final class ShowBookTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Tokens::actingAs(Accounts::of(Readers::ME), ProtectedResource::Api);
    }

    #[Test]
    public function it_shows_a_book_of_the_reader(): void
    {
        $book = Books::aBook('Dune', 'Frank Herbert');
        $this->app->make(BookRepository::class)->save($book);

        $this->getJson('/api/books/' . $book->id()->value)
            ->assertOk()
            ->assertJsonPath('data.id', $book->id()->value)
            ->assertJsonPath('data.title', 'Dune');
    }

    #[Test]
    public function the_book_of_another_reader_is_not_found(): void
    {
        $book = Books::aBook(reader: Readers::someoneElse());
        $this->app->make(BookRepository::class)->save($book);

        $this->getJson('/api/books/' . $book->id()->value)
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertExactJson([
                'type' => url('problems/unknown-book'),
                'title' => 'Unknown book',
                'status' => 404,
                'detail' => sprintf('No book with id "%s" in the library.', $book->id()->value),
            ]);
    }

    #[Test]
    public function an_id_that_is_not_a_uuid_designates_no_book(): void
    {
        $this->getJson('/api/books/dune')
            ->assertNotFound()
            ->assertJsonPath('type', url('problems/invalid-book-id'));
    }
}
