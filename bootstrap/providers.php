<?php

declare(strict_types=1);

use App\Library\Infrastructure\LibraryServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    LibraryServiceProvider::class,
];
