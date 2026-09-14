<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BookController::class, 'index'])->name('home');

Route::resource('books', BookController::class);

// 未実装機能の一時ルート（各機能の実装時に置き換える）
Route::redirect('/ranking', '/books')->name('ranking.index');
Route::redirect('/favorites', '/books')->name('favorites.index');
Route::redirect('/genres', '/books')->name('genres.index');

// TODO: お気に入り・レビュー機能の実装時にControllerのルートへ置き換える
Route::post('/books/{book}/favorite', function ($book) {
    return redirect()->route('books.show', $book);
})->name('favorites.toggle');

Route::post('/books/{book}/reviews', function ($book) {
    return redirect()->route('books.show', $book);
})->name('reviews.store');
