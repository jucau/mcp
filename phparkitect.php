<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\DependsOnlyOnTheseNamespaces;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/app');

    // Every rule below is written once per bounded context, never with a wildcard: App\*\Domain would let the
    // domain of one context depend on the domain of another.
    $contexts = ['Library'];

    $rules = [];

    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('App'))
        ->should(new ResideInOneOfTheseNamespaces(...array_map(static fn (string $context): string => "App\\{$context}", $contexts)))
        ->because('a class outside the listed bounded contexts would escape every rule: list its context first');

    foreach ($contexts as $context) {
        // An allow-list, not a ban-list (ADR-0006): the domain knows itself, PHP, and the one library whose work it
        // would be worse to rewrite. Anything else needs a decision, and a line here.
        $rules[] = Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces("App\\{$context}\\Domain"))
            ->should(new DependsOnlyOnTheseNamespaces(["App\\{$context}\\Domain", 'Ramsey\\Uuid']))
            ->because('the domain must stay pure: no framework, no protocol, no outer layer, no other context, no library it did not choose');

        // The same allow-list one layer out: the application orchestrates the domain through ports, so it knows the
        // domain, itself and PHP. Webmozart\Assert narrows types for PHPStan (ADR-0015) and nothing else.
        $rules[] = Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces("App\\{$context}\\Application"))
            ->should(new DependsOnlyOnTheseNamespaces(["App\\{$context}\\Domain", "App\\{$context}\\Application", 'Webmozart\\Assert']))
            ->because('the application orchestrates its own domain through ports, without infrastructure, framework, persistence or protocol');

        foreach ([['Mcp', 'Api'], ['Api', 'Mcp']] as [$adapter, $other]) {
            $rules[] = Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces("App\\{$context}\\Infrastructure\\{$adapter}"))
                ->should(new NotDependsOnTheseNamespaces(["App\\{$context}\\Infrastructure\\{$other}"]))
                ->because('each driving adapter owns its contract (ADR-0025): what both need belongs to the core');
        }
    }

    $config->add($classSet, ...$rules);
};
