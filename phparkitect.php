<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/app');

    $rules = [];

    // MCP is one driving adapter among others (ADR-0006): the protocol must never leak past Infrastructure.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('App\*\Domain'))
        ->should(new NotDependsOnTheseNamespaces([
            'Illuminate',
            'Laravel',
            'App\*\Application',
            'App\*\Infrastructure',
        ]))
        ->because('the domain must stay pure: no framework, no protocol, no outer layer');

    // Illuminate\Support (collections, helpers) stays allowed; direct persistence AND facades are banned —
    // a facade (DB::, Event::, Cache::, …) is hidden infrastructure that would bypass the ports.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('App\*\Application'))
        ->should(new NotDependsOnTheseNamespaces([
            'App\*\Infrastructure',
            'Illuminate\Database',
            'Illuminate\Support\Facades',
            'Laravel\Mcp',
        ]))
        ->because('the application orchestrates the domain through ports, without infrastructure, direct persistence, facades or protocol');

    $config->add($classSet, ...$rules);
};
