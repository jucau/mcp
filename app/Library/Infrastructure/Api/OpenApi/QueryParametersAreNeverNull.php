<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
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
                $parameter->schema->type = $parameter->schema->type->nullable(false);
            }
        }
    }
}
