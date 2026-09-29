<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    public function update(User $user, ReadingPlan $plan): bool
    {
        return $this->delete($user, $plan) && $plan->status !== ReadingPlanStatus::Completed;
    }

    public function delete(User $user, ReadingPlan $plan): bool
    {
        return $user->id === $plan->user_id;
    }

    public function complete(User $user, ReadingPlan $plan): bool
    {
        return $this->delete($user, $plan);
    }
}
