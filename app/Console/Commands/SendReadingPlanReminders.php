<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendReadingPlanReminders extends Command
{
    protected $signature = 'reading-plans:remind';

    protected $description = '期限切れの読書計画を更新し、リマインダー通知を作成する';

    /**
     * 期限切れの計画を更新し、対象者へDB通知を送信する。
     *
     * @return int コマンドの終了コード
     */
    public function handle(): int
    {
        $today = Carbon::today('Asia/Tokyo');
        ReadingPlan::where('status', ReadingPlanStatus::InProgress)->whereDate('target_date', '<', $today->toDateString())->update(['status' => ReadingPlanStatus::Expired]);
        foreach ([['three_days_before', 3, ReadingPlanStatus::InProgress], ['on_due_date', 0, ReadingPlanStatus::InProgress], ['three_days_after', -3, ReadingPlanStatus::Expired]] as [$timing,$offset,$status]) {
            $date = $today->copy()->addDays($offset)->toDateString();
            ReadingPlan::where('status', $status)->whereDate('target_date', $date)->select('id')->chunkById(100, function (Collection $plans) use ($timing, $status, $date): void {
                foreach ($plans as $candidate) {
                    DB::transaction(function () use ($candidate, $timing, $status, $date): void {
                        $plan = ReadingPlan::whereKey($candidate->id)->lockForUpdate()->first();
                        if (! $plan || $plan->status !== $status || $plan->target_date->toDateString() !== $date) {
                            return;
                        }
                        $user = $plan->user;
                        if (! $user->notifications()->where('type', ReadingPlanReminder::class)->where('data->reading_plan_id', $plan->id)->where('data->timing', $timing)->exists()) {
                            Notification::send($user, new ReadingPlanReminder($plan, $timing));
                        }
                    });
                }
            });
        }
        $this->info('読書計画の状態更新と通知処理が完了しました。');

        return self::SUCCESS;
    }
}
