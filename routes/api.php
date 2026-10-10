<?php

declare(strict_types=1);

use App\Library\Infrastructure\Api\AnnounceResourceMetadata;
use App\Library\Infrastructure\Api\Controllers\SearchBooksController;
use App\Library\Infrastructure\Api\Controllers\ShowBookController;
use App\Library\Infrastructure\Api\Controllers\ShowShelfController;
use App\Library\Infrastructure\Api\ServeTheAuthenticatedReader;
use App\Library\Infrastructure\Auth\ProtectedResource;
use App\Library\Infrastructure\Auth\RequireTokenFor;
use Illuminate\Support\Facades\Route;

Route::middleware([
    AnnounceResourceMetadata::class,
    'auth:api',
    RequireTokenFor::class . ':' . ProtectedResource::Api->value,
    ServeTheAuthenticatedReader::class,
])->group(function (): void {
    Route::get('/books', SearchBooksController::class);
    Route::get('/books/{book}', ShowBookController::class);
    Route::get('/shelves/{shelf}', ShowShelfController::class)->whereIn('shelf', array_keys(ShowShelfController::SHELVES));
});
