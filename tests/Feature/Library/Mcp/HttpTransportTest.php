<?php

declare(strict_types=1);

namespace Tests\Feature\Library\Mcp;

use App\Library\Domain\Book\BookRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Library\Books;
use Tests\TestCase;

final class HttpTransportTest extends TestCase
{
    use DatabaseTransactions;

    private const string MODERN = '2026-07-28';

    protected function setUp(): void
    {
        parent::setUp();

        config(['library.mcp_allowed_origins' => ['http://localhost:8090']]);
    }

    #[Test]
    public function it_answers_a_modern_request_without_any_handshake(): void
    {
        $this->app->make(BookRepository::class)->save(Books::aBook(title: 'Dune'));

        $response = $this->modern('tools/call', ['name' => 'search_books', 'arguments' => ['query' => 'dune']], name: 'search_books');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Mcp-Session-Id')
            ->assertJsonPath('id', 1)
            ->assertJsonPath('result.resultType', 'complete')
            ->assertJsonPath('result.structuredContent.books.0.title', 'Dune');
    }

    #[Test]
    public function it_tells_which_versions_it_speaks_when_discovered(): void
    {
        $this->modern('server/discover', [])
            ->assertOk()
            ->assertJsonPath('result.supportedVersions', [self::MODERN]);
    }

    #[Test]
    public function it_refuses_a_version_it_does_not_speak_and_lists_the_ones_it_does(): void
    {
        $this->modern('tools/list', [], version: '1900-01-01')
            ->assertBadRequest()
            ->assertJsonPath('error.code', -32022)
            ->assertJsonPath('error.data.supported', [self::MODERN])
            ->assertJsonPath('error.data.requested', '1900-01-01');
    }

    #[Test]
    public function it_refuses_headers_that_contradict_the_body(): void
    {
        $this->modern('tools/call', ['name' => 'search_books', 'arguments' => []], name: 'remove_book')
            ->assertBadRequest()
            ->assertJsonPath('error.code', -32020);
    }

    #[Test]
    public function it_refuses_a_modern_request_without_its_headers(): void
    {
        $this->postJson('/mcp', $this->modernBody('tools/list', []))
            ->assertBadRequest()
            ->assertJsonPath('error.code', -32020);
    }

    #[Test]
    public function it_answers_an_unknown_method_with_a_404(): void
    {
        $this->modern('books/burn', [])
            ->assertNotFound()
            ->assertJsonPath('error.code', -32601);
    }

    #[Test]
    public function it_still_serves_a_legacy_client_but_never_opens_a_session(): void
    {
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '0']],
        ])
            ->assertOk()
            ->assertHeaderMissing('Mcp-Session-Id')
            ->assertJsonPath('result.protocolVersion', '2025-11-25');
    }

    #[Test]
    public function it_accepts_a_notification_with_an_empty_202(): void
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        $response->assertAccepted();
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function it_has_no_stream_to_open_nor_session_to_close(): void
    {
        $this->get('/mcp')->assertMethodNotAllowed()->assertHeader('Allow', 'POST');
        $this->delete('/mcp')->assertMethodNotAllowed()->assertHeader('Allow', 'POST');
    }

    #[Test]
    public function it_forbids_a_page_from_a_foreign_origin(): void
    {
        $this->modern('tools/list', [], origin: 'http://evil.example:8090')
            ->assertForbidden()
            ->assertJsonMissingPath('id')
            ->assertJsonPath('error.message', 'The origin [http://evil.example:8090] is not allowed.');
    }

    #[Test]
    public function it_serves_a_page_from_an_allowed_origin(): void
    {
        $this->modern('tools/list', [], origin: 'http://localhost:8090')->assertOk();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return TestResponse<JsonResponse>
     */
    private function modern(string $method, array $params, ?string $name = null, string $version = self::MODERN, ?string $origin = null): TestResponse
    {
        $headers = array_filter([
            'MCP-Protocol-Version' => $version,
            'Mcp-Method' => $method,
            'Mcp-Name' => $name,
            'Origin' => $origin,
            'Accept' => 'application/json, text/event-stream',
        ]);

        return $this->postJson('/mcp', $this->modernBody($method, $params, $version), $headers);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function modernBody(string $method, array $params, string $version = self::MODERN): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => [
                ...$params,
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => $version,
                    'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
                ],
            ],
        ];
    }
}
