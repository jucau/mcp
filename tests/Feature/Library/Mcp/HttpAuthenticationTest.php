<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\BookRepository;
use App\Library\Infrastructure\Reader\AuthenticatedReader;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Tests\TestCase;

final class HttpAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function without_a_token_the_server_says_where_to_learn_how_to_get_one(): void
    {
        $this->mcp('tools/list')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', sprintf('Bearer realm="mcp", resource_metadata="%s"', url('/.well-known/oauth-protected-resource/mcp')));
    }

    #[Test]
    public function the_endpoint_answers_401_and_never_redirects_to_the_login_page(): void
    {
        $this->post('/mcp', [], ['Accept' => 'text/html'])
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    #[Test]
    public function the_protected_resource_names_its_authorization_server(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJson([
                'resource' => url('/mcp'),
                'authorization_servers' => [url('/')],
                'scopes_supported' => ['mcp:use'],
            ]);
    }

    #[Test]
    public function the_authorization_server_announces_its_endpoints_and_pkce(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJson([
                'issuer' => url('/'),
                'authorization_endpoint' => url('/oauth/authorize'),
                'token_endpoint' => url('/oauth/token'),
                'registration_endpoint' => url('/oauth/register'),
                'code_challenge_methods_supported' => ['S256'],
                'grant_types_supported' => ['authorization_code', 'refresh_token'],
            ]);
    }

    #[Test]
    public function the_token_decides_the_library_served_never_the_configured_reader(): void
    {
        $repository = $this->app->make(BookRepository::class);
        $repository->save(Books::aBook('Dune', 'Frank Herbert'));
        $repository->save(Books::aBook('Fondation', 'Isaac Asimov', reader: Readers::someoneElse()));
        Passport::actingAs(Accounts::of(Readers::SOMEONE_ELSE));

        $this->mcp('tools/call', ['name' => 'search_books', 'arguments' => []], name: 'search_books')
            ->assertOk()
            ->assertJsonPath('result.structuredContent.total', 1)
            ->assertJsonPath('result.structuredContent.books.0.title', 'Fondation');
    }

    #[Test]
    public function today_is_the_day_of_the_account_timezone(): void
    {
        $book = Books::aBook(reader: Readers::someoneElse());
        $this->app->make(BookRepository::class)->save($book);
        Passport::actingAs(Accounts::of(Readers::SOMEONE_ELSE, 'Pacific/Kiritimati'));
        $this->travelTo(new DateTimeImmutable('2026-10-04 12:00', new DateTimeZone('UTC')));

        $this->mcp('tools/call', ['name' => 'start_reading', 'arguments' => ['book_id' => $book->id()->value]], name: 'start_reading')
            ->assertOk()
            ->assertJsonPath('result.structuredContent.started_on', '2026-10-05');
    }

    #[Test]
    public function without_an_account_the_reader_cannot_be_known_rather_than_falling_back_on_the_configured_one(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->app->make(AuthenticatedReader::class)->id();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return TestResponse<JsonResponse>
     */
    private function mcp(string $method, array $params = [], ?string $name = null): TestResponse
    {
        $headers = array_filter([
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => $method,
            'Mcp-Name' => $name,
        ]);

        return $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => [...$params, '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
            ]],
        ], $headers);
    }
}
