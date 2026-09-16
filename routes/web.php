<<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BookController::class, 'index'])->name('home');

Route::resource('books', BookController::class);

Route::middleware('auth')->group(function () {
    Route::post(
        '/books/{book}/reviews',
        [ReviewController::class, 'store']
    )->name('reviews.store');

    Route::get(
        '/reviews/{review}/edit',
        [ReviewController::class, 'edit']
    )->name('reviews.edit');

    Route::put(
        '/reviews/{review}',
        [ReviewController::class, 'update']
    )->name('reviews.update');

    Route::delete(
        '/reviews/{review}',
        [ReviewController::class, 'destroy']
    )->name('reviews.destroy');

    Route::get(
        '/favorites',
        [FavoriteController::class, 'index']
    )->name('favorites.index');

    Route::post(
        '/books/{book}/favorite',
        [FavoriteController::class, 'toggle']
    )->name('favorites.toggle');

    Route::post(
        '/reviews/{review}/like',
        [ReviewLikeController::class, 'toggle']
    )->name('reviews.like');
});

Route::get(
    '/ranking',
    [RankingController::class, 'index']
)->name('ranking.index');

Route::middleware('auth')->group(function () {
    // 既存の認証必須ルート

    Route::resource('genres', GenreController::class);
});
