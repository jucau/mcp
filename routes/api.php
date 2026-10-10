<?php

declare(strict_types=1);

use App\Library\Infrastructure\Api\AnnounceResourceMetadata;
use App\Library\Infrastructure\Api\Controllers\AbandonReadingController;
use App\Library\Infrastructure\Api\Controllers\AcquireBookController;
use App\Library\Infrastructure\Api\Controllers\AddBookController;
use App\Library\Infrastructure\Api\Controllers\FinishReadingController;
use App\Library\Infrastructure\Api\Controllers\LendBookController;
use App\Library\Infrastructure\Api\Controllers\RateBookController;
use App\Library\Infrastructure\Api\Controllers\RemoveBookController;
use App\Library\Infrastructure\Api\Controllers\ReturnBookController;
use App\Library\Infrastructure\Api\Controllers\SearchBooksController;
use App\Library\Infrastructure\Api\Controllers\ShowBookController;
use App\Library\Infrastructure\Api\Controllers\ShowShelfController;
use App\Library\Infrastructure\Api\Controllers\StartReadingController;
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
    Route::post('/books', AddBookController::class);
    Route::get('/books/{book}', ShowBookController::class);
    Route::delete('/books/{book}', RemoveBookController::class);
    Route::post('/books/{book}/start-reading', StartReadingController::class);
    Route::post('/books/{book}/finish-reading', FinishReadingController::class);
    Route::post('/books/{book}/abandon-reading', AbandonReadingController::class);
    Route::post('/books/{book}/rate', RateBookController::class);
    Route::post('/books/{book}/lend', LendBookController::class);
    Route::post('/books/{book}/return', ReturnBookController::class);
    Route::post('/books/{book}/acquire', AcquireBookController::class);
    Route::get('/shelves/{shelf}', ShowShelfController::class)->whereIn('shelf', array_keys(ShowShelfController::SHELVES));
});
