<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
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
