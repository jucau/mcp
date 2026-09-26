<?php

declare(strict_types=1);

namespace RawClient;

use Closure;
use RuntimeException;

final class McpClient
{
    private int $lastId = 0;

    /**
     * @param resource $process
     * @param resource $input
     * @param resource $output
     * @param Closure(string): void $transcript
     */
    private function __construct(
        private $process,
        private $input,
        private $output,
        private readonly Closure $transcript,
    ) {
    }

    /**
     * @param list<string> $command
     * @param Closure(string): void $transcript
     */
    public static function launch(array $command, Closure $transcript): self
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);

        if ($process === false) {
            throw new RuntimeException('The server could not be started.');
        }

        return new self($process, $pipes[0], $pipes[1], $transcript);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function request(string $method, array $params = []): array
    {
        $id = ++$this->lastId;
        $this->send(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, ...$this->params($params)]);

        do {
            $message = $this->receive();
        } while (($message['id'] ?? null) !== $id);

        return $message;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function notify(string $method, array $params = []): void
    {
        $this->send(['jsonrpc' => '2.0', 'method' => $method, ...$this->params($params)]);
    }

    public function close(): int
    {
        fclose($this->input);
        fclose($this->output);

        return proc_close($this->process);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{params?: array<string, mixed>}
     */
    private function params(array $params): array
    {
        return $params === [] ? [] : ['params' => $params];
    }

    /**
     * @param array<string, mixed> $message
     */
    private function send(array $message): void
    {
        $line = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ($this->transcript)('→ ' . $line);
        fwrite($this->input, $line . "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function receive(): array
    {
        $line = fgets($this->output);

        if ($line === false) {
            throw new RuntimeException('The server closed the connection.');
        }

        $line = rtrim($line, "\n");
        ($this->transcript)('← ' . $line);

        $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($message)) {
            throw new RuntimeException('The server sent something else than a JSON-RPC message: ' . $line);
        }

        /** @var array<string, mixed> $message */
        return $message;
    }
}
