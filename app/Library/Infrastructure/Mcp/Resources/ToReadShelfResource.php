<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Infrastructure\Mcp\Shelf;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Uri('library://shelves/to-read')]
#[Name('shelf_to_read')]
#[Title('To read')]
#[Description('The books the user owns, has at home and has not started yet, sorted by title.')]
final class ToReadShelfResource extends ShelfResource
{
    protected function shelf(): Shelf
    {
        return Shelf::ToRead;
    }
}
