<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Rating;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Resources\BookResource;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Announced;
use Tests\Support\Library\Books;
use Tests\Support\Library\ReadContent;
use Tests\Support\Library\Readers;
use Tests\Support\Library\Tokens;
use Tests\TestCase;

final class BookResourceTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_is_announced_as_a_uri_template(): void
    {
        $this->assertEquals([
            'name' => 'book',
            'title' => 'A book',
            'description' => "One book of the user's library, with everything the library knows about it: reading, rating, loan.",
            'uriTemplate' => 'library://books/{id}',
            'mimeType' => 'application/json',
        ], Announced::resourceTemplate(BookResource::class));
    }

    #[Test]
    public function it_reads_the_book_its_uri_names(): void
    {
        $dune = Books::aBook(isbn: '9780441172719');
        $dune->startReading(new DateTimeImmutable('2026-01-10'));
        $dune->finishReading(new DateTimeImmutable('2026-02-01'));
        $dune->rate(Rating::fromInt(5));
        $this->app->make(BookRepository::class)->save($dune);

        $content = ReadContent::of(LibraryServer::resource(BookResource::class, ['id' => $dune->id()->value])->assertOk());

        $this->assertSame('library://books/' . $dune->id()->value, $content['uri']);
        $this->assertSame('application/json', $content['mimeType']);
        $this->assertSame([
            'id' => $dune->id()->value,
            'title' => 'Dune',
            'author' => 'Frank Herbert',
            'isbn' => '9780441172719',
            'owned' => true,
            'reading_status' => 'read',
            'started_on' => '2026-01-10',
            'finished_on' => '2026-02-01',
            'rating' => 5,
            'lent_to' => null,
            'lent_on' => null,
        ], $content['json']);
    }

    #[Test]
    public function an_unknown_book_is_a_protocol_error_that_still_says_why_in_production(): void
    {
        config(['app.debug' => false]);
        $id = BookId::generate()->value;

        LibraryServer::resource(BookResource::class, ['id' => $id])
            ->assertHasErrors([sprintf('No book with id "%s" in the library.', $id)]);
    }

    #[Test]
    public function a_malformed_id_is_refused_like_in_the_tools(): void
    {
        config(['app.debug' => false]);

        LibraryServer::resource(BookResource::class, ['id' => 'dune'])
            ->assertHasErrors(['"dune" is not a valid book id: expected a UUID.']);
    }

    #[Test]
    public function a_book_it_cannot_read_is_the_client_s_mistake_not_a_server_failure(): void
    {
        config(['app.debug' => false]);
        $id = BookId::generate()->value;

        $this->readOverHttp("library://books/{$id}")
            ->assertBadRequest()
            ->assertJsonPath('error.code', -32602)
            ->assertJsonPath('error.message', sprintf('No book with id "%s" in the library.', $id));
        $this->readOverHttp('library://books/dune')
            ->assertBadRequest()
            ->assertJsonPath('error.code', -32602)
            ->assertJsonPath('error.message', '"dune" is not a valid book id: expected a UUID.');
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function readOverHttp(string $uri): TestResponse
    {
        Tokens::actingAs(Accounts::of(Readers::ME));

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'resources/read', 'params' => ['uri' => $uri]]);
    }
}
