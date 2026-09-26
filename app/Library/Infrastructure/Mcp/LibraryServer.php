<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp;

use App\Library\Infrastructure\Mcp\Tools\AddBookTool;
use App\Library\Infrastructure\Mcp\Tools\SearchBooksTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Marque-page')]
#[Version('0.1.0')]
#[Instructions(<<<'MARKDOWN'
    Marque-page is the user's personal library: the books they own or wish to read, where they are in each
    reading, what they thought of it and whom they lent it to.

    Every book has an id (a UUID). To act on a book the user names, first find its id with `search_books`,
    then pass that id: never guess or invent one. Books without an ISBN are common; do not make one up.
    MARKDOWN)]
final class LibraryServer extends Server
{
    protected array $tools = [
        SearchBooksTool::class,
        AddBookTool::class,
    ];
}
