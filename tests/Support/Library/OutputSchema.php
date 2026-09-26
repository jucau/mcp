<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use JsonSchema\Validator;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Transport\JsonRpcResponse;
use PHPUnit\Framework\Assert as PHPUnit;
use ReflectionProperty;
use Webmozart\Assert\Assert;

final class OutputSchema
{
    /**
     * laravel/mcp announces the outputSchema but never checks a result against it: the tests do.
     */
    public static function assertRespected(TestResponse $response, string $tool): void
    {
        $rpc = new ReflectionProperty(TestResponse::class, 'response')->getValue($response);
        Assert::isInstanceOf($rpc, JsonRpcResponse::class);

        $result = $rpc->toArray()['result'] ?? null;
        Assert::isArray($result);
        Assert::keyExists($result, 'structuredContent');

        $schema = Announced::tool($tool)['outputSchema'] ?? null;
        Assert::isArray($schema, sprintf('%s announces no outputSchema.', $tool));

        $validator = new Validator();
        $content = json_decode(json_encode($result['structuredContent'], JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
        $validator->validate($content, json_decode(json_encode($schema, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR));

        PHPUnit::assertTrue($validator->isValid(), sprintf('%s returned content outside its outputSchema: %s', $tool, json_encode($validator->getErrors(), JSON_THROW_ON_ERROR)));
    }
}
