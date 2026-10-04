<?php

namespace App\Services;

use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

class ReadingReportService
{
    /**
     * 本人の全期間のレビューをCollectionで集計する。
     *
     * @param  User  $user  処理対象のユーザー
     * @return array 処理結果の配列
     */
    public function summarize(User $user): array
    {
        $reviews = $user->reviews()->with('book.genres')->get();
        $counts = $reviews->countBy('rating');
        $genres = $reviews->flatMap(fn (Review $review): Collection => $review->book->genres
            ->map(fn (Genre $genre): array => [
                'id' => $genre->id, 'name' => $genre->name, 'rating' => $review->rating,
            ]))
            ->groupBy('id')->map(fn (Collection $items): array => [
                'id' => $items->first()['id'],
                'name' => $items->first()['name'],
                'average_rating' => $items->avg('rating'),
                'count' => $items->count(),
            ])->sortBy([['average_rating', 'desc'], ['count', 'desc'], ['id', 'asc']])
            ->take(5)->values();

        return [
            'summary' => [
                'total_reviews' => $reviews->count(),
                'books_read' => $reviews->pluck('book_id')->unique()->count(),
                'average_rating' => $reviews->isEmpty() ? null : round($reviews->avg('rating'), 1),
            ],
            'rating_distribution' => collect(range(1, 5))->map(fn (int $rating): int => $counts[$rating] ?? 0),
            'top_rated_books' => $reviews->where('rating', '>=', 4)
                ->sortBy([['rating', 'desc'], ['created_at', 'desc'], ['id', 'desc']])
                ->take(5)->values()->map(fn (Review $review): array => [
                    'id' => $review->book->id, 'title' => $review->book->title,
                    'author' => $review->book->author, 'rating' => $review->rating,
                ]),
            'genre_ratings' => $genres,
        ];
    }
}
