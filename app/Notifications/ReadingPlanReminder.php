<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    public function __construct(public ReadingPlan $plan, public string $timing) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = match ($this->timing) {
            'three_days_before' => '読書の期日まであと3日です', 'on_due_date' => '今日は読書の期日です', 'three_days_after' => '読書の期日から3日経過しました'
        };

        return ['reading_plan_id' => $this->plan->id, 'timing' => $this->timing, 'title' => $title, 'body' => '「'.$this->plan->book->title.'」の期日は'.$this->plan->target_date->format('Y-m-d').'です。', 'url' => route('reading-plans.index')];
    }
}
