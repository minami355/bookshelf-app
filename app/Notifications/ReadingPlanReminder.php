<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    /**
     * 処理に必要な初期設定を行う。
     *
     * @param  ReadingPlan  $plan  対象の読書計画
     * @param  string  $timing  リマインダーの通知タイミング
     */
    public function __construct(public ReadingPlan $plan, public string $timing) {}

    /**
     * 通知の保存に使用するチャネルを返す。
     *
     * @param  object  $notifiable  通知の受信者
     * @return array 処理結果の配列
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * 読書計画ID・本文・遷移先をDB保存用に組み立てる。
     *
     * @param  object  $notifiable  通知の受信者
     * @return array 処理結果の配列
     */
    public function toDatabase(object $notifiable): array
    {
        $title = match ($this->timing) {
            'three_days_before' => '読書の期日まであと3日です', 'on_due_date' => '今日は読書の期日です', 'three_days_after' => '読書の期日から3日経過しました'
        };

        return ['reading_plan_id' => $this->plan->id, 'timing' => $this->timing, 'title' => $title, 'body' => '「'.$this->plan->book->title.'」の期日は'.$this->plan->target_date->format('Y-m-d').'です。', 'url' => route('reading-plans.index')];
    }
}
