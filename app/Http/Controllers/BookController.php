<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 処理に必要な初期設定を行う。
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'show']);
    }

    /**
     * 一覧を取得してレスポンスを返す。
     *
     * @param  IndexBookRequest  $request  入力と認証情報を持つリクエスト
     * @return View 表示する画面
     */
    public function index(IndexBookRequest $request): View
    {
        $filters = $request->validated();
        $keyword = trim($filters['keyword'] ?? '');
        $query = Book::with('genres')->withAvg('reviews', 'rating')
            ->when($keyword !== '', function (Builder $query) use ($keyword): void {
                $query->where(fn (Builder $query): Builder => $query->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%"));
            })
            ->when(isset($filters['genre_id']), fn (Builder $query): Builder => $query->whereHas(
                'genres', fn (Builder $query): Builder => $query->whereKey($filters['genre_id'])
            ));

        match ($filters['sort']) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'title' => $query->orderBy('title')->orderByDesc('id'),
            'rating' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $books = $query->paginate(10)->appends($request->only('keyword', 'genre_id', 'sort'));
        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * 登録フォームを表示する。
     *
     * @return View 表示する画面
     */
    public function create(): View
    {
        $genres = Genre::orderBy('name')->get();

        return view('books.create', compact('genres'));
    }

    /**
     * 検証済みの入力から登録し、結果を返す。
     *
     * @param  StoreBookRequest  $request  入力と認証情報を持つリクエスト
     * @return RedirectResponse 処理後の遷移先
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $genreIds = $validated['genre_ids'];

        unset($validated['genre_ids']);

        $book = DB::transaction(function () use ($request, $validated, $genreIds): Book {
            $book = $request->user()->books()->create($validated);
            $book->genres()->sync($genreIds);

            return $book;
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 対象の詳細を取得してレスポンスを返す。
     *
     * @param  Book  $book  対象書籍
     * @return View 表示する画面
     */
    public function show(Book $book): View
    {
        $book->load([
            'user',
            'genres',
            'favorites',
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return view('books.show', compact('book'));
    }

    /**
     * 認可後に編集フォームを表示する。
     *
     * @param  Book  $book  対象書籍
     * @return View 表示する画面
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');
        $genres = Genre::orderBy('name')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 対象を更新し、結果を返す。
     *
     * @param  UpdateBookRequest  $request  入力と認証情報を持つリクエスト
     * @param  Book  $book  対象書籍
     * @return RedirectResponse 処理後の遷移先
     */
    public function update(
        UpdateBookRequest $request,
        Book $book
    ): RedirectResponse {
        $this->authorize('update', $book);

        $validated = $request->validated();
        $genreIds = $validated['genre_ids'];

        unset($validated['genre_ids']);

        DB::transaction(function () use ($book, $validated, $genreIds): void {
            $book->update($validated);
            $book->genres()->sync($genreIds);
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    /**
     * 対象を削除し、結果を返す。
     *
     * @param  Book  $book  対象書籍
     * @return RedirectResponse 処理後の遷移先
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
