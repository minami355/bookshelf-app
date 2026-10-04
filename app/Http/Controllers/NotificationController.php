<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * 本人の通知を新しい順に20件ずつ表示する。
     *
     * @param  Request  $request  入力と認証情報を持つリクエスト
     * @return View 表示する画面
     */
    public function index(Request $request): View
    {
        return view('notifications.index', ['notifications' => $request->user()->notifications()->orderByDesc('created_at')->orderBy('id')->paginate(20)]);
    }

    /**
     * 受信者の認可後、通知を既読にする。
     *
     * @param  Request  $request  入力と認証情報を持つリクエスト
     * @param  string  $id  対象通知ID
     * @return RedirectResponse 処理後の遷移先
     */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = DatabaseNotification::findOrFail($id);
        $this->authorize('read', $notification);
        $notification->markAsRead();

        return redirect()->route('notifications.index');
    }
}
