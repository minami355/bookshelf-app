<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ReviewController;
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
});

// 未実装機能の一時ルート（各機能の実装時に置き換える）
Route::redirect('/ranking', '/books')->name('ranking.index');
Route::redirect('/favorites', '/books')->name('favorites.index');
Route::redirect('/genres', '/books')->name('genres.index');

// TODO: お気に入り機能の実装時にControllerのルートへ置き換える
Route::post('/books/{book}/favorite', function ($book) {
    return redirect()->route('books.show', $book);
})->name('favorites.toggle');

// TODO: レビューいいね機能の実装時にControllerのルートへ置き換える
Route::post('/reviews/{review}/like', function () {
    return back();
})->middleware('auth')->name('reviews.like');
