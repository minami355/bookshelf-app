<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\Api\V1\BookDetailResource;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * 一覧を取得してレスポンスを返す。
     *
     * @param  IndexBookRequest  $request  入力と認証情報を持つリクエスト
     * @return AnonymousResourceCollection ページネーション付きAPI一覧
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $keyword = trim($validated['keyword'] ?? '');

        /** @var LengthAwarePaginator $books */
        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($keyword !== '', function (Builder $query) use ($keyword): void {
                $query->where(function (Builder $query) use ($keyword): void {
                    $query
                        ->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author', 'like', "%{$keyword}%")
                        ->orWhere('isbn', 'like', "%{$keyword}%");
                });
            })
            ->when(
                isset($validated['genre_id']),
                fn (Builder $query): Builder => $query->whereHas(
                    'genres',
                    fn (Builder $query): Builder => $query->whereKey($validated['genre_id'])
                )
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20);

        $books->withQueryString();

        return BookResource::collection($books);
    }

    /**
     * 検証済みの入力から登録し、結果を返す。
     *
     * @param  StoreBookRequest  $request  入力と認証情報を持つリクエスト
     * @return JsonResponse 処理結果のJSONレスポンス
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $genreIds = $validated['genre_ids'];

        unset($validated['genre_ids']);

        $book = DB::transaction(function () use ($request, $validated, $genreIds): Book {
            $book = $request->user()->books()->create($validated);
            $book->genres()->sync($genreIds);

            return $book;
        });

        $book->load(['genres', 'reviews.user'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return (new BookDetailResource($book))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 対象の詳細を取得してレスポンスを返す。
     *
     * @param  Book  $book  対象書籍
     * @return BookDetailResource 書籍詳細のAPIリソース
     */
    public function show(Book $book): BookDetailResource
    {
        $book->load(['genres', 'reviews.user'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookDetailResource($book);
    }

    /**
     * 対象を更新し、結果を返す。
     *
     * @param  UpdateBookRequest  $request  入力と認証情報を持つリクエスト
     * @param  Book  $book  対象書籍
     * @return BookDetailResource 書籍詳細のAPIリソース
     */
    public function update(
        UpdateBookRequest $request,
        Book $book
    ): BookDetailResource {
        $this->authorize('update', $book);

        $validated = $request->validated();
        $genreIds = $validated['genre_ids'];

        unset($validated['genre_ids'], $validated['user_id']);

        // PUTは全項目更新とし、省略された任意項目もクリアする。
        $validated += array_fill_keys(['isbn', 'published_date', 'description', 'image_url'], null);

        DB::transaction(function () use ($book, $validated, $genreIds): void {
            $book->update($validated);
            $book->genres()->sync($genreIds);
        });

        $book->load(['genres', 'reviews.user'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookDetailResource($book);
    }

    /**
     * 対象を削除し、結果を返す。
     *
     * @param  Book  $book  対象書籍
     * @return Response 処理結果のHTTPレスポンス
     */
    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        DB::transaction(function () use ($book): void {
            $book->delete();
        });

        return response()->noContent();
    }
}
