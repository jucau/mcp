<?php

declare(strict_types=1);

namespace Tests\Feature\RawClient;

use App\Library\Domain\Book\BookRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use RawClient\HttpMcpClient;
use RawClient\HttpReply;
use Tests\Support\Library\Accounts;
use Tests\Support\Library\Books;
use Tests\Support\Library\Readers;
use Tests\TestCase;

final class HttpMcpClientTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var list<string>
     */
    private array $transcript = [];

    #[Test]
    public function it_calls_a_tool_without_any_handshake_and_the_server_accepts_its_headers(): void
    {
        $this->app->make(BookRepository::class)->save(Books::aBook(title: 'Dune'));

        $answer = $this->client()->request('tools/call', ['name' => 'search_books', 'arguments' => ['query' => 'dune']]);

        $this->assertSame(200, $answer['status']);
        $this->assertSame(1, $answer['message']['id']);
        $this->assertSame('Dune', data_get($answer['message'], 'result.structuredContent.books.0.title'));
        $this->assertSame('→ POST MCP-Protocol-Version: 2026-07-28 Mcp-Method: tools/call Mcp-Name: search_books', $this->transcript[0]);
    }

    #[Test]
    public function every_request_carries_its_version_and_capabilities_in_its_meta(): void
    {
        $this->client()->request('server/discover');

        $body = json_decode(trim($this->transcript[1]), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame([
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => [],
            'io.modelcontextprotocol/clientInfo' => ['name' => 'raw-client', 'version' => '0.1.0'],
        ], data_get($body, 'params._meta'));
    }

    #[Test]
    public function each_response_carries_the_id_of_its_request(): void
    {
        $client = $this->client();

        $discover = $client->request('server/discover');
        $tools = $client->request('tools/list');

        $this->assertSame(1, $discover['message']['id']);
        $this->assertSame(2, $tools['message']['id']);
        $this->assertCount(10, (array) data_get($tools['message'], 'result.tools'));
    }

    #[Test]
    public function a_name_that_is_not_plain_ascii_travels_base64_encoded_and_the_server_decodes_it(): void
    {
        $answer = $this->client()->request('resources/read', ['uri' => 'library://books/hypérion']);

        $this->assertStringEndsWith('Mcp-Name: =?base64?' . base64_encode('library://books/hypérion') . '?=', $this->transcript[0]);
        $this->assertNotSame(-32020, data_get($answer['message'], 'error.code'));
    }

    #[Test]
    public function it_sends_its_token_but_never_writes_it_in_the_transcript(): void
    {
        $sent = [];
        $client = new HttpMcpClient(
            static function (array $headers, string $body) use (&$sent): HttpReply {
                $sent = $headers;

                return new HttpReply(200, 'application/json', '{"jsonrpc":"2.0","id":1,"result":{}}');
            },
            function (string $line): void {
                $this->transcript[] = $line;
            },
            token: 's3cr3t',
        );

        $client->request('tools/list');

        $this->assertSame('Bearer s3cr3t', $sent['Authorization'] ?? null);
        $this->assertSame('→ POST MCP-Protocol-Version: 2026-07-28 Mcp-Method: tools/list Authorization: Bearer …', $this->transcript[0]);
        $this->assertStringNotContainsString('s3cr3t', implode("\n", $this->transcript));
    }

    #[Test]
    public function a_version_the_server_does_not_speak_is_refused_with_the_ones_it_does(): void
    {
        $answer = $this->client(version: '1900-01-01')->request('tools/list');

        $this->assertSame(400, $answer['status']);
        $this->assertSame(-32022, data_get($answer['message'], 'error.code'));
        $this->assertSame(['2026-07-28'], data_get($answer['message'], 'error.data.supported'));
    }

    #[Test]
    public function an_unknown_method_is_a_json_rpc_error_and_a_404(): void
    {
        $answer = $this->client()->request('books/burn');

        $this->assertSame(404, $answer['status']);
        $this->assertSame(['code' => -32601, 'message' => 'The method [books/burn] was not found.'], $answer['message']['error']);
    }

    #[Test]
    public function in_a_stream_it_skips_the_notifications_and_returns_the_message_that_answers_its_request(): void
    {
        $stream = "data: {\"jsonrpc\":\"2.0\",\"method\":\"notifications/progress\",\"params\":{\"progress\":1}}\n\n"
            . "data: {\"jsonrpc\":\"2.0\",\"id\":1,\"result\":{\"done\":true}}\n\n";
        $client = new HttpMcpClient(
            static fn (array $headers, string $body): HttpReply => new HttpReply(200, 'text/event-stream', $stream),
            static function (string $line): void {
            },
        );

        $answer = $client->request('tools/call', ['name' => 'import_books']);

        $this->assertSame(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['done' => true]], $answer['message']);
    }

    private function client(string $version = HttpMcpClient::VERSION): HttpMcpClient
    {
        Passport::actingAs(Accounts::of(Readers::ME));

        return new HttpMcpClient(
            fn (array $headers, string $body): HttpReply => $this->viaTheKernel($headers, $body),
            function (string $line): void {
                $this->transcript[] = $line;
            },
            $version,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function viaTheKernel(array $headers, string $body): HttpReply
    {
        $response = $this->call('POST', '/mcp', server: $this->transformHeadersToServerVars($headers), content: $body);

        return new HttpReply(
            $response->getStatusCode(),
            (string) $response->headers->get('Content-Type'),
            (string) $response->getContent(),
        );
    }
}
