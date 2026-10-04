<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * 一覧を取得してレスポンスを返す。
     *
     * @return View 表示する画面
     */
    public function index(): View
    {
        $genres = Genre::query()
            ->withCount('books')
            ->orderBy('name')
            ->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * 登録フォームを表示する。
     *
     * @return View 表示する画面
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * 検証済みの入力から登録し、結果を返す。
     *
     * @param  StoreGenreRequest  $request  入力と認証情報を持つリクエスト
     * @return RedirectResponse 処理後の遷移先
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを登録しました。');
    }

    /**
     * 対象の詳細を取得してレスポンスを返す。
     *
     * @param  Genre  $genre  処理に使用するgenre
     * @return View 表示する画面
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()
            ->with('genres')
            ->orderBy('title')
            ->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * 認可後に編集フォームを表示する。
     *
     * @param  Genre  $genre  処理に使用するgenre
     * @return View 表示する画面
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * 対象を更新し、結果を返す。
     *
     * @param  UpdateGenreRequest  $request  入力と認証情報を持つリクエスト
     * @param  Genre  $genre  処理に使用するgenre
     * @return RedirectResponse 処理後の遷移先
     */
    public function update(
        UpdateGenreRequest $request,
        Genre $genre
    ): RedirectResponse {
        $genre->update($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを更新しました。');
    }

    /**
     * 対象を削除し、結果を返す。
     *
     * @param  Genre  $genre  処理に使用するgenre
     * @return RedirectResponse 処理後の遷移先
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        if ($genre->books()->exists()) {
            return redirect()
                ->route('genres.index')
                ->with(
                    'error',
                    'このジャンルには書籍が紐付いているため削除できません。'
                );
        }

        $genre->delete();

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
