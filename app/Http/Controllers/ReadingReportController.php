<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingReportController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = Review::query()->where('reviews.user_id', $request->user()->id);
        $summary = (clone $reviews)
            ->selectRaw('COUNT(*) AS total_reviews, COUNT(DISTINCT book_id) AS books_read, AVG(rating) AS average_rating')
            ->first();
        $counts = (clone $reviews)->selectRaw('rating, COUNT(*) AS total')
            ->groupBy('rating')->pluck('total', 'rating');

        $topBooks = (clone $reviews)->with('book:id,title,author')
            ->where('rating', '>=', 4)
            ->orderByDesc('rating')->orderByDesc('created_at')->orderByDesc('id')
            ->limit(5)->get()->map(fn (Review $review) => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ]);

        $genres = (clone $reviews)
            ->join('book_genre', 'reviews.book_id', '=', 'book_genre.book_id')
            ->join('genres', 'book_genre.genre_id', '=', 'genres.id')
            ->select('genres.id', 'genres.name')
            ->selectRaw('AVG(reviews.rating) AS average_rating, COUNT(*) AS count')
            ->groupBy('genres.id', 'genres.name')
            ->orderByDesc('average_rating')->orderByDesc('count')->orderBy('genres.id')
            ->limit(5)->get();

        $stats = [
            'summary' => [
                'total_reviews' => (int) $summary->total_reviews,
                'books_read' => (int) $summary->books_read,
                'average_rating' => $summary->average_rating === null
                    ? null : round((float) $summary->average_rating, 1),
            ],
            'rating_distribution' => collect(range(1, 5))
                ->map(fn (int $rating) => (int) ($counts[$rating] ?? 0)),
            'top_rated_books' => $topBooks,
            'genre_ratings' => $genres,
        ];

        return view('reports.index', compact('stats'));
    }
}
