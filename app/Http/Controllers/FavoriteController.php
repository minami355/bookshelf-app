<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * 一覧を取得してレスポンスを返す。
     *
     * @param  Request  $request  入力と認証情報を持つリクエスト
     * @return View 表示する画面
     */
    public function index(Request $request): View
    {
        $books = $request->user()
            ->favoriteBooks()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * 追加と解除を切り替え、元の画面へ戻る。
     *
     * @param  Request  $request  入力と認証情報を持つリクエスト
     * @param  Book  $book  対象書籍
     * @return RedirectResponse 処理後の遷移先
     */
    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $request->user()
            ->favoriteBooks()
            ->toggle($book->id);

        return back();
    }
}
