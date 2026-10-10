<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Library\Domain\Book\BookRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Tests\TestCase;

final class IssueTokenTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function the_token_it_issues_opens_the_library_of_its_account(): void
    {
        $account = Accounts::of(Readers::SOMEONE_ELSE);
        $this->app->make(BookRepository::class)->save(Books::aBook('Fondation', 'Isaac Asimov', reader: Readers::someoneElse()));

        $this->assertSame(0, Artisan::call('library:token', ['email' => $account->email, '--resource' => 'mcp']));
        $token = trim(Artisan::output());

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'search_books', 'arguments' => [], '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
            ]],
        ], [
            'Authorization' => 'Bearer ' . $token,
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => 'tools/call',
            'Mcp-Name' => 'search_books',
        ])
            ->assertOk()
            ->assertJsonPath('result.structuredContent.books.0.title', 'Fondation');
    }

    #[Test]
    public function it_refuses_a_resource_it_does_not_know(): void
    {
        $this->assertSame(1, Artisan::call('library:token', ['email' => Accounts::of(Readers::ME)->email, '--resource' => 'elsewhere']));
    }

    #[Test]
    public function it_refuses_an_unknown_account(): void
    {
        $this->assertSame(1, Artisan::call('library:token', ['email' => 'nobody@marque-page.test']));
    }
}
