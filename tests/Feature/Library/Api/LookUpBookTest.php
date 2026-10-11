<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Api;

use App\Library\Application\Book\Catalog\BookCatalog;
use App\Library\Application\Book\Catalog\CatalogEntry;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\InMemoryBookCatalog;

final class LookUpBookTest extends ApiTestCase
{
    private InMemoryBookCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new InMemoryBookCatalog();
        $this->app->instance(BookCatalog::class, $this->catalog);
    }

    #[Test]
    public function it_gives_what_the_catalog_knows_of_an_isbn(): void
    {
        $this->catalog->knows(new CatalogEntry('9780441172719', 'Dune', 'Frank Herbert', 1965, 896));

        $this->getJson('/api/catalog/0-441-17271-7')
            ->assertOk()
            ->assertExactJson(['data' => [
                'isbn' => '9780441172719',
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'published_in' => 1965,
                'pages' => 896,
            ]]);
    }

    #[Test]
    public function an_entry_may_come_with_nothing_but_its_title(): void
    {
        $this->catalog->knows(new CatalogEntry('9780441172719', 'Dune'));

        $this->getJson('/api/catalog/9780441172719')
            ->assertOk()
            ->assertExactJson(['data' => ['isbn' => '9780441172719', 'title' => 'Dune', 'author' => null, 'published_in' => null, 'pages' => null]]);
    }

    #[Test]
    public function an_isbn_the_catalog_does_not_know_is_not_found(): void
    {
        $this->getJson('/api/catalog/9780441172719')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertExactJson([
                'type' => url('problems/not-in-catalog'),
                'title' => 'Not in catalog',
                'status' => 404,
                'detail' => 'The open catalog knows no book with ISBN 9780441172719.',
            ]);
    }

    #[Test]
    public function a_catalog_that_is_down_makes_the_service_unavailable(): void
    {
        $this->catalog->goDown();

        $this->getJson('/api/catalog/9780441172719')
            ->assertServiceUnavailable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertExactJson([
                'type' => url('problems/catalog-unavailable'),
                'title' => 'Catalog unavailable',
                'status' => 503,
                'detail' => 'The open catalog cannot be reached right now.',
            ]);
    }

    #[Test]
    public function an_invalid_isbn_is_refused_without_asking_the_catalog(): void
    {
        $this->getJson('/api/catalog/dune')
            ->assertUnprocessable()
            ->assertJsonPath('type', url('problems/invalid-isbn'));

        $this->assertSame([], $this->catalog->asked());
    }
}
