<?php

declare(strict_types=1);

namespace Tests\Feature\RawClient;

use PHPUnit\Framework\Attributes\Test;
use RawClient\McpClient;
use Tests\TestCase;

final class McpClientTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $transcript = [];

    #[Test]
    public function it_opens_a_session_and_the_server_answers_with_its_version_and_capabilities(): void
    {
        $client = $this->launch();

        $initialize = $client->request('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'raw-client', 'version' => '0.1.0'],
        ]);
        $client->notify('notifications/initialized');

        $this->assertSame(1, $initialize['id']);
        $this->assertSame('2025-11-25', data_get($initialize, 'result.protocolVersion'));
        $capabilities = data_get($initialize, 'result.capabilities');
        $this->assertIsArray($capabilities);
        $this->assertSame(['tools', 'resources', 'prompts', 'completions'], array_keys($capabilities));
        $this->assertSame('Marque-page', data_get($initialize, 'result.serverInfo.name'));
        $this->assertSame(0, $client->close());
    }

    #[Test]
    public function the_server_answers_an_unknown_version_with_its_own(): void
    {
        $client = $this->launch();

        $initialize = $client->request('initialize', [
            'protocolVersion' => '2099-01-01',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'raw-client', 'version' => '0.1.0'],
        ]);

        $this->assertSame('2025-11-25', data_get($initialize, 'result.protocolVersion'));
        $client->close();
    }

    #[Test]
    public function each_response_carries_the_id_of_its_request(): void
    {
        $client = $this->initialized();

        $ping = $client->request('ping');
        $tools = $client->request('tools/list');

        $this->assertSame(2, $ping['id']);
        $this->assertSame(3, $tools['id']);
        $this->assertCount(10, (array) data_get($tools, 'result.tools'));
        $client->close();
    }

    #[Test]
    public function an_unknown_method_is_a_json_rpc_error(): void
    {
        $client = $this->initialized();

        $response = $client->request('books/burn');

        $this->assertSame(['code' => -32601, 'message' => 'The method [books/burn] was not found.'], $response['error']);
        $this->assertArrayNotHasKey('result', $response);
        $client->close();
    }

    #[Test]
    public function the_transcript_shows_every_line_in_both_directions_and_a_notification_gets_no_answer(): void
    {
        $client = $this->initialized();
        $client->close();

        $this->assertCount(3, $this->transcript);
        $this->assertSame('→ {"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"raw-client","version":"0.1.0"}}}', $this->transcript[0]);
        $this->assertStringStartsWith('← {"jsonrpc":"2.0","id":1,"result":', $this->transcript[1]);
        $this->assertSame('→ {"jsonrpc":"2.0","method":"notifications/initialized"}', $this->transcript[2]);
    }

    private function initialized(): McpClient
    {
        $client = $this->launch();
        $client->request('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'raw-client', 'version' => '0.1.0'],
        ]);
        $client->notify('notifications/initialized');

        return $client;
    }

    private function launch(): McpClient
    {
        return McpClient::launch(
            [PHP_BINARY, base_path('artisan'), 'mcp:start', 'library'],
            function (string $line): void {
                $this->transcript[] = $line;
            },
        );
    }
}
