<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Laravel's `nullable` becomes `["integer", "null"]`, which means something for a JSON body and nothing for a query
 * string: a query parameter is there or absent, never null.
 */
final class QueryParametersAreNeverNull implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->in === 'query' && $parameter->schema instanceof Schema) {
                $parameter->schema->type = $this->withoutNull($parameter->schema->type);
            }
        }
    }

    private function withoutNull(Type $type): Type
    {
        if ($type instanceof AnyOf) {
            $others = array_values(array_filter($type->items, static fn (Type $item): bool => ! $item instanceof NullType));

            if (count($others) === 1) {
                return $this->withoutNull($others[0]);
            }
        }

        return $type->nullable(false);
    }
}
