<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth;

enum ProtectedResource: string
{
    case Mcp = 'mcp';

    public static function fromUri(mixed $uri): ?self
    {
        foreach (self::cases() as $resource) {
            if ($resource->uri() === $uri) {
                return $resource;
            }
        }

        return null;
    }

    public function uri(): string
    {
        return url($this->value);
    }
}
