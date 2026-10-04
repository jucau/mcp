<?php

declare(strict_types=1);

namespace RawClient;

use Closure;
use RuntimeException;
use stdClass;

final class HttpMcpClient
{
    public const string VERSION = '2026-07-28';

    private const array NAMED_METHODS = ['tools/call' => 'name', 'prompts/get' => 'name', 'resources/read' => 'uri'];

    private int $lastId = 0;

    /**
     * @param Closure(array<string, string>, string): HttpReply $post
     * @param Closure(string): void $transcript
     */
    public function __construct(
        private readonly Closure $post,
        private readonly Closure $transcript,
        private readonly string $version = self::VERSION,
    ) {
    }

    /**
     * @param Closure(string): void $transcript
     */
    public static function over(string $endpoint, Closure $transcript, string $version = self::VERSION): self
    {
        return new self(
            static fn (array $headers, string $body): HttpReply => self::curl($endpoint, $headers, $body),
            $transcript,
            $version,
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{status: int, message: array<string, mixed>}
     */
    public function request(string $method, array $params = []): array
    {
        $id = ++$this->lastId;
        $body = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => [...$params, '_meta' => $this->meta()],
        ];

        $reply = $this->post($this->headers($method, $params), $body);

        return ['status' => $reply->status, 'message' => $this->messageFor($id, $reply)];
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(): array
    {
        return [
            'io.modelcontextprotocol/protocolVersion' => $this->version,
            'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
            'io.modelcontextprotocol/clientInfo' => ['name' => 'raw-client', 'version' => '0.1.0'],
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function headers(string $method, array $params): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => $this->version,
            'Mcp-Method' => $method,
        ];

        $nameField = self::NAMED_METHODS[$method] ?? null;
        $name = $nameField === null ? null : ($params[$nameField] ?? null);

        if (is_string($name)) {
            $headers['Mcp-Name'] = $this->headerSafe($name);
        }

        return $headers;
    }

    private function headerSafe(string $value): string
    {
        $isPlainAscii = preg_match('/^[\x21-\x7E](?:[\x20-\x7E]*[\x21-\x7E])?$/', $value) === 1;
        $looksEncoded = str_starts_with($value, '=?base64?') && str_ends_with($value, '?=');

        return $isPlainAscii && ! $looksEncoded ? $value : '=?base64?' . base64_encode($value) . '?=';
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     */
    private function post(array $headers, array $body): HttpReply
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        ($this->transcript)('→ POST ' . $this->describe($headers));
        ($this->transcript)('  ' . $json);

        $reply = ($this->post)($headers, $json);

        ($this->transcript)("← {$reply->status} {$reply->contentType}");
        ($this->transcript)('  ' . $reply->body);

        return $reply;
    }

    /**
     * @param array<string, string> $headers
     */
    private function describe(array $headers): string
    {
        $mcpHeaders = array_filter($headers, static fn (string $name): bool => stripos($name, 'mcp') !== false, ARRAY_FILTER_USE_KEY);

        return implode(' ', self::lines($mcpHeaders));
    }

    /**
     * @param array<string, string> $headers
     */
    private static function curl(string $endpoint, array $headers, string $body): HttpReply
    {
        $http = curl_init($endpoint);
        curl_setopt_array($http, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => self::lines($headers),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($http);

        if (! is_string($response)) {
            throw new RuntimeException('The server could not be reached: ' . curl_error($http));
        }

        return new HttpReply(
            curl_getinfo($http, CURLINFO_RESPONSE_CODE),
            (string) curl_getinfo($http, CURLINFO_CONTENT_TYPE),
            $response,
        );
    }

    /**
     * @param array<string, string> $headers
     *
     * @return list<string>
     */
    private static function lines(array $headers): array
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function messageFor(int $id, HttpReply $reply): array
    {
        if (! str_starts_with($reply->contentType, 'text/event-stream')) {
            return $this->decode($reply->body);
        }

        preg_match_all('/^data: (.*)$/m', $reply->body, $events);

        foreach ($events[1] as $event) {
            $message = $this->decode($event);

            if (($message['id'] ?? null) === $id) {
                return $message;
            }
        }

        throw new RuntimeException("No answer to request {$id} in: {$reply->body}");
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $message = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($message)) {
            throw new RuntimeException('The server sent something else than a JSON-RPC message: ' . $json);
        }

        /** @var array<string, mixed> $message */
        return $message;
    }
}
