<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', ['notifications' => $request->user()->notifications()->orderByDesc('created_at')->orderBy('id')->paginate(20)]);
    }

    public function read(Request $request, string $id)
    {
        $notification = DatabaseNotification::findOrFail($id);
        abort_unless($notification->notifiable_type === $request->user()->getMorphClass()
            && (string) $notification->notifiable_id === (string) $request->user()->getKey(), 403);
        $notification->markAsRead();

        return redirect()->route('notifications.index');
    }
}
