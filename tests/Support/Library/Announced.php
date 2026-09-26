<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Infrastructure\Mcp\LibraryServer;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Testing\TestListResponse;
use ReflectionProperty;
use Webmozart\Assert\Assert;

final class Announced
{
    /**
     * @return array<array-key, mixed>
     */
    public static function tool(string $name): array
    {
        return self::find(self::listed(LibraryServer::tools()), 'name', $name);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function resource(string $uri): array
    {
        return self::find(self::listed(LibraryServer::resources()), 'uri', $uri);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function prompt(string $name): array
    {
        return self::find(self::listed(LibraryServer::prompts()), 'name', $name);
    }

    /**
     * The SDK has no test helper for resources/templates/list: this is the entry it lists, serialized the same way.
     *
     * @param class-string<Resource> $template
     *
     * @return array<array-key, mixed>
     */
    public static function resourceTemplate(string $template): array
    {
        $resource = resolve($template);
        Assert::isInstanceOf($resource, Resource::class);

        $entry = self::wire($resource->toArray());
        Assert::isArray($entry);

        return $entry;
    }

    /**
     * @param list<array<array-key, mixed>> $entries
     *
     * @return array<array-key, mixed>
     */
    private static function find(array $entries, string $key, string $value): array
    {
        $indexed = array_column($entries, null, $key);
        Assert::keyExists($indexed, $value, 'Nothing announced under %s.');
        Assert::isArray($indexed[$value]);

        return $indexed[$value];
    }

    /**
     * The SDK keeps the listed items protected and only offers assertRegistered(), which instantiates the
     * primitives without the container.
     *
     * @return list<array<array-key, mixed>>
     */
    private static function listed(TestListResponse $response): array
    {
        $items = self::wire(new ReflectionProperty(TestListResponse::class, 'items')->getValue($response));
        Assert::isList($items);
        Assert::allIsArray($items);

        return $items;
    }

    /**
     * The JSON round trip compares what goes on the wire, not the SDK's PHP objects.
     */
    private static function wire(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }
}
