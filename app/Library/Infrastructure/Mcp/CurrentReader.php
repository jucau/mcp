<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp;

use App\Library\Domain\Book\ReaderId;
use Illuminate\Contracts\Config\Repository;
use Webmozart\Assert\Assert;

final readonly class CurrentReader
{
    public function __construct(private Repository $config)
    {
    }

    public function id(): ReaderId
    {
        $reader = $this->config->get('library.reader');
        Assert::stringNotEmpty($reader, 'No reader configured: set LIBRARY_READER to the id of the reader to serve.');

        return ReaderId::fromString($reader);
    }
}
