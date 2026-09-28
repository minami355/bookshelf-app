<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/tokens', [AuthController::class, 'store']);

    Route::delete('/tokens/current', [AuthController::class, 'destroy'])
        ->middleware('auth:sanctum');

    // 公開API
    Route::get('/books', [BookController::class, 'index']);

    Route::get('/books/{book}', [BookController::class, 'show'])
        ->missing(fn () => response()->json([
            'error' => '書籍が見つかりませんでした。',
        ], 404));

    // 認証必須API
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{book}', [BookController::class, 'update'])
            ->missing(fn () => response()->json([
                'error' => '書籍が見つかりませんでした。',
            ], 404));
        Route::delete('/books/{book}', [BookController::class, 'destroy'])
            ->missing(fn () => response()->json([
                'error' => '書籍が見つかりませんでした。',
            ], 404));
    });
});
