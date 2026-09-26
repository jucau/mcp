<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Transport\JsonRpcResponse;
use ReflectionProperty;
use Webmozart\Assert\Assert;

final class PromptMessages
{
    /**
     * The messages a prompts/get returned, as role and text. TestResponse only offers assertSee(), a substring
     * match.
     *
     * @return list<array{role: string, text: string}>
     */
    public static function of(TestResponse $response): array
    {
        $rpc = new ReflectionProperty(TestResponse::class, 'response')->getValue($response);
        Assert::isInstanceOf($rpc, JsonRpcResponse::class);

        $result = $rpc->toArray()['result'] ?? null;
        Assert::isArray($result);
        Assert::isList($result['messages'] ?? null);

        $messages = [];

        foreach ($result['messages'] as $message) {
            Assert::isArray($message);
            Assert::string($message['role'] ?? null);
            Assert::isArray($message['content'] ?? null);
            Assert::same($message['content']['type'] ?? null, 'text');
            Assert::string($message['content']['text'] ?? null);

            $messages[] = ['role' => $message['role'], 'text' => $message['content']['text']];
        }

        return $messages;
    }
}
