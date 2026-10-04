<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPolicy
{
    /**
     * 対象操作（read）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  DatabaseNotification  $notification  対象通知
     * @return bool 許可または条件成立ならtrue
     */
    public function read(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->getKey();
    }
}
