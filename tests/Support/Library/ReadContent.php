<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Transport\JsonRpcResponse;
use ReflectionProperty;
use Webmozart\Assert\Assert;

final class ReadContent
{
    /**
     * The one content block a resources/read returned, its JSON text decoded. TestResponse only offers
     * assertSee(), a substring match.
     *
     * @return array{uri: mixed, mimeType: mixed, json: mixed}
     */
    public static function of(TestResponse $response): array
    {
        $rpc = new ReflectionProperty(TestResponse::class, 'response')->getValue($response);
        Assert::isInstanceOf($rpc, JsonRpcResponse::class);

        $result = $rpc->toArray()['result'] ?? null;
        Assert::isArray($result);

        $contents = $result['contents'] ?? null;
        Assert::isList($contents);
        Assert::count($contents, 1);
        Assert::isArray($contents[0]);
        Assert::string($contents[0]['text'] ?? null);

        return [
            'uri' => $contents[0]['uri'] ?? null,
            'mimeType' => $contents[0]['mimeType'] ?? null,
            'json' => json_decode($contents[0]['text'], true, flags: JSON_THROW_ON_ERROR),
        ];
    }
}
