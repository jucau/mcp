<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Mcp\Resources;

use App\Library\Application\Book\Query\BookSearch;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Uri('library://shelves/wishlist')]
#[Name('shelf_wishlist')]
#[Title('Wishlist')]
#[Description('The books the user wishes to read but does not own yet, sorted by title.')]
final class WishlistShelfResource extends ShelfResource
{
    protected function shelf(int $page, int $perPage): BookSearch
    {
        return new BookSearch(owned: false, page: $page, perPage: $perPage);
    }
}
