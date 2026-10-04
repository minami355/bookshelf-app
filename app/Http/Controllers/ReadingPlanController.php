<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Services\ReadingPlanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 本人の読書計画を期日順に表示する。
     *
     * @param  ReadingPlanRequest  $request  入力と認証情報を持つリクエスト
     * @return View 表示する画面
     */
    public function index(ReadingPlanRequest $request): View
    {
        $currentStatus = $request->validated('status');

        /** @var LengthAwarePaginator $readingPlans */
        $readingPlans = $request->user()->readingPlans()
            ->with('book')
            ->when($currentStatus, fn (Builder $query): Builder => $query->where('status', $currentStatus))
            ->orderBy('target_date')
            ->orderBy('id')
            ->paginate(10);

        $readingPlans->withQueryString();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * 読書計画の登録フォームを表示する。
     *
     * @return View 表示する画面
     */
    public function create(): View
    {
        return view('reading-plans.create', ['books' => Book::orderBy('title')->get()]);
    }

    /**
     * ユーザーをロックし、読書中の重複計画を防いで登録する。
     *
     * @param  ReadingPlanRequest  $request  入力と認証情報を持つリクエスト
     * @param  ReadingPlanService  $plans  読書計画の業務処理サービス
     * @return RedirectResponse 処理後の遷移先
     */
    public function store(ReadingPlanRequest $request, ReadingPlanService $plans): RedirectResponse
    {
        $plans->create($request->user(), $request->validated());

        return redirect()->route('reading-plans.index')->with('success', '読書計画を登録しました。');
    }

    /**
     * 所有者と計画状態を確認して編集画面を表示する。
     *
     * @param  ReadingPlan  $plan  対象の読書計画
     * @return View 表示する画面
     */
    public function edit(ReadingPlan $plan): View
    {
        $this->authorize('update', $plan);

        return view('reading-plans.edit', ['readingPlan' => $plan->load('book')]);
    }

    /**
     * 重複を確認し、期限切れの計画は読書中に戻して期日を更新する。
     *
     * @param  ReadingPlanRequest  $request  入力と認証情報を持つリクエスト
     * @param  ReadingPlan  $plan  対象の読書計画
     * @param  ReadingPlanService  $plans  読書計画の業務処理サービス
     * @return RedirectResponse 処理後の遷移先
     */
    public function update(ReadingPlanRequest $request, ReadingPlan $plan, ReadingPlanService $plans): RedirectResponse
    {
        $this->authorize('update', $plan);
        $plans->update($request->user(), $plan, $request->validated('target_date'));

        return redirect()->route('reading-plans.index')->with('success', '期日を更新しました。');
    }

    /**
     * 初回の読了時刻を保存し、再操作では変更しない。
     *
     * @param  ReadingPlan  $plan  対象の読書計画
     * @param  ReadingPlanService  $plans  読書計画の業務処理サービス
     * @return RedirectResponse 処理後の遷移先
     */
    public function complete(ReadingPlan $plan, ReadingPlanService $plans): RedirectResponse
    {
        $this->authorize('complete', $plan);
        $plans->complete($plan);

        return redirect()->route('reading-plans.index')->with('success', '読了しました。');
    }

    /**
     * 計画と関連通知を同一トランザクションで削除する。
     *
     * @param  ReadingPlan  $plan  対象の読書計画
     * @param  ReadingPlanService  $plans  読書計画の業務処理サービス
     * @return RedirectResponse 処理後の遷移先
     */
    public function destroy(ReadingPlan $plan, ReadingPlanService $plans): RedirectResponse
    {
        $this->authorize('delete', $plan);
        $plans->destroy($plan);

        return redirect()->route('reading-plans.index')->with('success', '読書計画を削除しました。');
    }
}
