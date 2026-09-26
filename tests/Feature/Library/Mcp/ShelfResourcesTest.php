<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Borrower;
use App\Library\Infrastructure\Mcp\LibraryServer;
use App\Library\Infrastructure\Mcp\Resources\LentShelfResource;
use App\Library\Infrastructure\Mcp\Resources\ReadingShelfResource;
use App\Library\Infrastructure\Mcp\Resources\ToReadShelfResource;
use App\Library\Infrastructure\Mcp\Resources\WishlistShelfResource;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Mcp\Server\Resource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Announced;
use Tests\Support\Library\Books;
use Tests\Support\Library\ReadContent;
use Tests\TestCase;
use Webmozart\Assert\Assert;

final class ShelfResourcesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return iterable<string, array{class-string<Resource>, string, string, string, string, list<string>}>
     */
    public static function shelves(): iterable
    {
        yield 'to read' => [
            ToReadShelfResource::class, 'library://shelves/to-read', 'shelf_to_read', 'To read',
            'The books the user owns, has at home and has not started yet, sorted by title.',
            ['Dune'],
        ];
        yield 'reading' => [
            ReadingShelfResource::class, 'library://shelves/reading', 'shelf_reading', 'Reading now',
            'The books the user is reading now, with the day they started, sorted by title.',
            ['Fondation'],
        ];
        yield 'lent' => [
            LentShelfResource::class, 'library://shelves/lent', 'shelf_lent', 'Lent',
            'The books the user lent, with whom and since when, sorted by title.',
            ['Neuromancien'],
        ];
        yield 'wishlist' => [
            WishlistShelfResource::class, 'library://shelves/wishlist', 'shelf_wishlist', 'Wishlist',
            'The books the user wishes to read but does not own yet, sorted by title.',
            ['Hypérion'],
        ];
    }

    /**
     * @param class-string<Resource> $resource
     * @param list<string> $titles
     */
    #[Test]
    #[DataProvider('shelves')]
    public function each_shelf_is_announced_under_a_fixed_uri(string $resource, string $uri, string $name, string $title, string $description, array $titles): void
    {
        $this->assertEquals([
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'uri' => $uri,
            'mimeType' => 'application/json',
        ], Announced::resource($uri));
    }

    /**
     * @param class-string<Resource> $resource
     * @param list<string> $titles
     */
    #[Test]
    #[DataProvider('shelves')]
    public function each_shelf_holds_only_its_books(string $resource, string $uri, string $name, string $title, string $description, array $titles): void
    {
        $this->given(Books::aBook('Dune', 'Frank Herbert'));
        $reading = Books::aBook('Fondation', 'Isaac Asimov');
        $reading->startReading(new DateTimeImmutable('2026-09-01'));
        $this->given($reading);
        $lent = Books::aBook('Neuromancien', 'William Gibson');
        $lent->lendTo(Borrower::fromString('Léa'), new DateTimeImmutable('2026-09-01'));
        $this->given($lent);
        $this->given(Books::aBook('Hypérion', 'Dan Simmons', owned: false));

        $content = ReadContent::of(LibraryServer::resource($resource)->assertOk());

        $this->assertSame($uri, $content['uri']);
        $this->assertSame('application/json', $content['mimeType']);
        $this->assertSame($titles, array_column($this->books($content['json']), 'title'));
    }

    #[Test]
    public function a_shelf_shows_every_book_the_library_knows_about_it(): void
    {
        $lent = Books::aBook('Neuromancien', 'William Gibson', '9780441569595');
        $lent->lendTo(Borrower::fromString('Léa'), new DateTimeImmutable('2026-09-01'));
        $this->given($lent);

        $content = ReadContent::of(LibraryServer::resource(LentShelfResource::class)->assertOk());

        $this->assertSame([[
            'id' => $lent->id()->value,
            'title' => 'Neuromancien',
            'author' => 'William Gibson',
            'isbn' => '9780441569595',
            'owned' => true,
            'reading_status' => 'to_read',
            'started_on' => null,
            'finished_on' => null,
            'rating' => null,
            'lent_to' => 'Léa',
            'lent_on' => '2026-09-01',
        ]], $this->books($content['json']));
    }

    #[Test]
    public function an_empty_shelf_reads_as_no_books(): void
    {
        $content = ReadContent::of(LibraryServer::resource(WishlistShelfResource::class)->assertOk());

        $this->assertSame([], $this->books($content['json']));
    }

    #[Test]
    public function a_shelf_is_read_whole_however_many_books_it_holds(): void
    {
        for ($i = 1; $i <= 105; ++$i) {
            $this->given(Books::aBook(sprintf('Tome %03d', $i), 'Divers'));
        }

        $content = ReadContent::of(LibraryServer::resource(ToReadShelfResource::class)->assertOk());

        $titles = array_column($this->books($content['json']), 'title');
        $this->assertCount(105, $titles);
        $this->assertSame('Tome 001', $titles[0]);
        $this->assertSame('Tome 105', $titles[104]);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function books(mixed $json): array
    {
        Assert::isArray($json);
        $this->assertSame(['books'], array_keys($json));
        Assert::isList($json['books']);
        Assert::allIsArray($json['books']);

        return $json['books'];
    }

    private function given(Book $book): void
    {
        $this->app->make(BookRepository::class)->save($book);
    }
}
