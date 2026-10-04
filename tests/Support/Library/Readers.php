<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Library\Domain\Book\ReaderId;

final class Readers
{
    public const string ME = '0b6f4f0e-3c5e-4d2a-9f1b-6a1c2d3e4f50';

    public const string SOMEONE_ELSE = '9e8d7c6b-5a49-4382-a716-0f1e2d3c4b5a';

    public static function me(): ReaderId
    {
        return ReaderId::fromString(self::ME);
    }

    public static function someoneElse(): ReaderId
    {
        return ReaderId::fromString(self::SOMEONE_ELSE);
    }
}
