<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * 対象操作（update）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  Review  $review  対象レビュー
     * @return bool 許可または条件成立ならtrue
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * 対象操作（delete）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  Review  $review  対象レビュー
     * @return bool 許可または条件成立ならtrue
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
