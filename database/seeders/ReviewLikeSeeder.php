<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        Review::all()->each(function (Review $review) use ($users): void {
            $candidateUserIds = $users
                ->where('id', '!=', $review->user_id)
                ->pluck('id');

            $likeCount = random_int(0, 3);

            if ($likeCount === 0) {
                return;
            }

            $likedUserIds = $candidateUserIds
                ->random($likeCount)
                ->all();

            $review->likedByUsers()
                ->syncWithoutDetaching($likedUserIds);
        });
    }
}
