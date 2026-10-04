<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * 検証済みの入力から登録し、結果を返す。
     *
     * @param  StoreReviewRequest  $request  入力と認証情報を持つリクエスト
     * @param  Book  $book  対象書籍
     * @return RedirectResponse 処理後の遷移先
     */
    public function store(
        StoreReviewRequest $request,
        Book $book
    ): RedirectResponse {
        $book->reviews()->create([
            'user_id' => $request->user()->id,
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
    }

    /**
     * 認可後に編集フォームを表示する。
     *
     * @param  Review  $review  対象レビュー
     * @return View 表示する画面
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('review'));
    }

    /**
     * 対象を更新し、結果を返す。
     *
     * @param  UpdateReviewRequest  $request  入力と認証情報を持つリクエスト
     * @param  Review  $review  対象レビュー
     * @return RedirectResponse 処理後の遷移先
     */
    public function update(
        UpdateReviewRequest $request,
        Review $review
    ): RedirectResponse {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()
            ->route('books.show', $review->book)
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * 対象を削除し、結果を返す。
     *
     * @param  Review  $review  対象レビュー
     * @return RedirectResponse 処理後の遷移先
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $book = $review->book;

        $review->delete();

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを削除しました。');
    }
}
