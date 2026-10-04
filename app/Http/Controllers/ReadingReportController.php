<?php

namespace App\Http\Controllers;

use App\Services\ReadingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingReportController extends Controller
{
    /**
     * ログインユーザーの読書レポートを表示する。
     *
     * @param  Request  $request  入力と認証情報を持つリクエスト
     * @param  ReadingReportService  $reports  読書レポート集計サービス
     * @return View 表示する画面
     */
    public function index(Request $request, ReadingReportService $reports): View
    {
        return view('reports.index', ['stats' => $reports->summarize($request->user())]);
    }
}
