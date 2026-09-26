<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Infrastructure\Mcp\LibraryServer;
use Laravel\Mcp\Server\Testing\TestListResponse;
use ReflectionProperty;
use Webmozart\Assert\Assert;

final class AnnouncedTools
{
    /**
     * @return array<array-key, mixed>
     */
    public static function named(string $name): array
    {
        $tools = array_column(self::all(), null, 'name');
        Assert::keyExists($tools, $name, 'No tool named %s is announced by tools/list.');
        Assert::isArray($tools[$name]);

        return $tools[$name];
    }

    /**
     * The SDK keeps the listed items protected and only offers assertRegistered(), which instantiates the tools
     * without the container. The JSON round trip compares what goes on the wire, not the SDK's PHP objects.
     *
     * @return list<array<array-key, mixed>>
     */
    private static function all(): array
    {
        $items = new ReflectionProperty(TestListResponse::class, 'items')->getValue(LibraryServer::tools());
        $items = json_decode(json_encode($items, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        Assert::isList($items);
        Assert::allIsArray($items);

        return $items;
    }
}
