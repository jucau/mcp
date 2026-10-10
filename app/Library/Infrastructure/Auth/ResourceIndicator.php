<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Auth;

/**
 * Carries the resource a token is being issued for across the Passport hooks that never see it together. Taking
 * it empties it: a resource left behind must never land on the next token issued by the same process.
 */
final class ResourceIndicator
{
    private ?ProtectedResource $resource = null;

    public function indicate(?ProtectedResource $resource): void
    {
        $this->resource = $resource;
    }

    public function take(): ?ProtectedResource
    {
        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }
}
