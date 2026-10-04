<?php

namespace App\Services;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** 読書計画の状態変更と同時更新時の整合性を管理する。 */
class ReadingPlanService
{
    /**
     * ユーザーをロックし、重複を再確認して読書計画を登録する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  array  $data  検証済みの登録データ
     * @return void 戻り値なし
     */
    public function create(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->checkDuplicate($user, (int) $data['book_id']);
            $user->readingPlans()->create($data + ['status' => ReadingPlanStatus::InProgress]);
        });
    }

    /**
     * ロック後に認可と重複を再確認し、期日と読書中状態を保存する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  ReadingPlan  $plan  対象の読書計画
     * @param  string  $targetDate  更新後の期日（Y-m-d）
     * @return void 戻り値なし
     */
    public function update(User $user, ReadingPlan $plan, string $targetDate): void
    {
        DB::transaction(function () use ($user, $plan, $targetDate): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $plan = ReadingPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('update', $plan);
            $this->checkDuplicate($user, $plan->book_id, $plan->id);
            $plan->update(['target_date' => $targetDate, 'status' => ReadingPlanStatus::InProgress]);
        });
    }

    /**
     * 初回の読了日時を保存し、計画を読了にする。
     *
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return void 戻り値なし
     */
    public function complete(ReadingPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $plan = ReadingPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($plan->status !== ReadingPlanStatus::Completed) {
                $plan->update(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);
            }
        });
    }

    /**
     * 計画と関連通知を同一トランザクションで削除する。
     *
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return void 戻り値なし
     */
    public function destroy(ReadingPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $plan = ReadingPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $plan->user->notifications()->where('data->reading_plan_id', $plan->id)->delete();
            $plan->delete();
        });
    }

    /**
     * 同じユーザーと書籍に別の読書中計画があれば拒否する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  int  $bookId  対象書籍ID
     * @param  ?int  $excludedPlanId  重複判定から除外する計画ID
     * @return void 戻り値なし
     */
    private function checkDuplicate(User $user, int $bookId, ?int $excludedPlanId = null): void
    {
        $query = $user->readingPlans()->where('book_id', $bookId)->where('status', ReadingPlanStatus::InProgress);
        if ($excludedPlanId) {
            $query->whereKeyNot($excludedPlanId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([$excludedPlanId ? 'target_date' : 'book_id' => 'この書籍には読書中の計画がすでにあります。']);
        }
    }
}
