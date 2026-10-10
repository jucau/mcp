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
    Route::get('/books', SearchBooksController::class)->name('searchBooks');
    Route::post('/books', AddBookController::class)->name('addBook');
    Route::get('/books/{book}', ShowBookController::class)->name('showBook');
    Route::delete('/books/{book}', RemoveBookController::class)->name('removeBook');
    Route::post('/books/{book}/start-reading', StartReadingController::class)->name('startReading');
    Route::post('/books/{book}/finish-reading', FinishReadingController::class)->name('finishReading');
    Route::post('/books/{book}/abandon-reading', AbandonReadingController::class)->name('abandonReading');
    Route::post('/books/{book}/rate', RateBookController::class)->name('rateBook');
    Route::post('/books/{book}/lend', LendBookController::class)->name('lendBook');
    Route::post('/books/{book}/return', ReturnBookController::class)->name('returnBook');
    Route::post('/books/{book}/acquire', AcquireBookController::class)->name('acquireBook');
    Route::get('/shelves/{shelf}', ShowShelfController::class)->name('showShelf');
});
