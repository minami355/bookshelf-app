<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'show']);
    }

    public function index(IndexBookRequest $request): View
    {
        $filters = $request->validated();
        $keyword = trim($filters['keyword'] ?? '');
        $query = Book::with('genres')->withAvg('reviews', 'rating')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(fn ($query) => $query->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%"));
            })
            ->when(isset($filters['genre_id']), fn ($query) => $query->whereHas(
                'genres', fn ($query) => $query->whereKey($filters['genre_id'])
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

    public function create(): View
    {
        $genres = Genre::orderBy('name')->get();

        return view('books.create', compact('genres'));
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $genreIds = $validated['genres'];

        unset($validated['genres']);

        $book = DB::transaction(function () use ($request, $validated, $genreIds): Book {
            $book = $request->user()->books()->create($validated);
            $book->genres()->sync($genreIds);

            return $book;
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

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

    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');
        $genres = Genre::orderBy('name')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    public function update(
        UpdateBookRequest $request,
        Book $book
    ): RedirectResponse {
        $this->authorize('update', $book);

        $validated = $request->validated();
        $genreIds = $validated['genres'];

        unset($validated['genres']);

        DB::transaction(function () use ($book, $validated, $genreIds): void {
            $book->update($validated);
            $book->genres()->sync($genreIds);
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
