<?php

declare(strict_types=1);

namespace RawClient;

final readonly class HttpReply
{
    public function __construct(
        public int $status,
        public string $contentType,
        public string $body,
    ) {
    }
}
