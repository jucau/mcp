<?php

declare(strict_types=1);

namespace App\Library\Infrastructure;

use DateTimeImmutable;
use Webmozart\Assert\Assert;

final readonly class ValidatedData
{
    /**
     * @param array<array-key, mixed> $validated
     */
    private function __construct(private array $validated)
    {
    }

    public static function of(mixed $validated): self
    {
        Assert::isArray($validated);

        return new self($validated);
    }

    public function string(string $name): string
    {
        $value = $this->validated[$name] ?? null;
        Assert::string($value);

        return $value;
    }

    public function stringOrNull(string $name): ?string
    {
        $value = $this->validated[$name] ?? null;
        Assert::nullOrString($value);

        return $value;
    }

    public function dayOrNull(string $name): ?DateTimeImmutable
    {
        $value = $this->stringOrNull($name);

        return $value === null ? null : new DateTimeImmutable($value);
    }

    public function boolOrNull(string $name): ?bool
    {
        $value = $this->validated[$name] ?? null;
        Assert::nullOrBoolean($value);

        return $value;
    }

    public function int(string $name): int
    {
        $value = $this->validated[$name] ?? null;
        Assert::integer($value);

        return $value;
    }

    public function intOr(string $name, int $default): int
    {
        $value = $this->validated[$name] ?? $default;
        Assert::integer($value);

        return $value;
    }
}
