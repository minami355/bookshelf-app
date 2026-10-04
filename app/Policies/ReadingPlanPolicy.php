<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * 対象操作（update）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return bool 許可または条件成立ならtrue
     */
    public function update(User $user, ReadingPlan $plan): bool
    {
        return $this->delete($user, $plan) && $plan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * 対象操作（delete）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return bool 許可または条件成立ならtrue
     */
    public function delete(User $user, ReadingPlan $plan): bool
    {
        return $user->id === $plan->user_id;
    }

    /**
     * 対象操作（complete）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return bool 許可または条件成立ならtrue
     */
    public function complete(User $user, ReadingPlan $plan): bool
    {
        return $this->delete($user, $plan);
    }
}
