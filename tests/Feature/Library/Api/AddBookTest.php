<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Application\Book\Query\BookQueries;
use App\Library\Application\Book\Query\BookView;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Webmozart\Assert\Assert;

final class AddBookTest extends ApiTestCase
{
    #[Test]
    public function it_adds_a_book_and_says_where_it_lives(): void
    {
        $response = $this->postJson('/api/books', ['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '978-0441172719'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Dune')
            ->assertJsonPath('data.isbn', '9780441172719')
            ->assertJsonPath('data.owned', true)
            ->assertJsonPath('data.reading_status', 'to_read');

        $id = $response->json('data.id');
        Assert::string($id);
        $response->assertHeader('Location', url('/api/books/' . $id));
        $this->assertInstanceOf(BookView::class, $this->app->make(BookQueries::class)->find(Readers::me(), $id));
    }

    #[Test]
    public function a_book_wished_for_goes_to_the_wishlist(): void
    {
        $this->postJson('/api/books', ['title' => 'Hypérion', 'author' => 'Dan Simmons', 'owned' => false])
            ->assertCreated()
            ->assertJsonPath('data.owned', false);
    }

    /**
     * @param array<string, mixed> $body
     */
    #[Test]
    #[TestWith([['author' => 'Frank Herbert'], 'title'])]
    #[TestWith([['title' => 'Dune'], 'author'])]
    #[TestWith([['title' => 'Dune', 'author' => 'Frank Herbert', 'owned' => 'false'], 'owned'])]
    #[TestWith([['title' => 'Dune', 'author' => 'Frank Herbert', 'owned' => 0], 'owned'])]
    public function a_body_of_the_wrong_shape_is_refused_field_by_field(array $body, string $field): void
    {
        $this->postJson('/api/books', $body)
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', url('problems/invalid-request'))
            ->assertJsonValidationErrors([$field]);
    }

    #[Test]
    public function an_isbn_already_in_the_library_is_a_conflict(): void
    {
        $this->given(Books::aBook('Dune', 'Frank Herbert', '9780441172719'));

        $this->postJson('/api/books', ['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9780441172719'])
            ->assertConflict()
            ->assertJsonPath('type', url('problems/duplicate-isbn'))
            ->assertJsonPath('detail', 'A book with ISBN 9780441172719 is already in the library.');
    }
}
