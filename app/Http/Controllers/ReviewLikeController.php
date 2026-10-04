<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    /**
     * 追加と解除を切り替え、元の画面へ戻る。
     *
     * @param  Request  $request  入力と認証情報を持つリクエスト
     * @param  Review  $review  対象レビュー
     * @return RedirectResponse 処理後の遷移先
     */
    public function toggle(
        Request $request,
        Review $review
    ): RedirectResponse {
        $request->user()
            ->likedReviews()
            ->toggle($review->id);

        return back();
    }
}
