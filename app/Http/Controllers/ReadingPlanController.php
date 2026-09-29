<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\ReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReadingPlanController extends Controller
{
    public function index(ReadingPlanRequest $request)
    {
        $currentStatus = $request->validated('status');
        $readingPlans = $request->user()->readingPlans()->with('book')->when($currentStatus, fn ($q) => $q->where('status', $currentStatus))->orderBy('target_date')->orderBy('id')->paginate(10)->withQueryString();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    public function create()
    {
        return view('reading-plans.create', ['books' => Book::orderBy('title')->get()]);
    }

    public function store(ReadingPlanRequest $request)
    {
        DB::transaction(function () use ($request) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->checkDuplicate($request->user(), (int) $request->validated('book_id'));
            $request->user()->readingPlans()->create($request->validated() + ['status' => ReadingPlanStatus::InProgress]);
        });

        return redirect()->route('reading-plans.index')->with('success', '読書計画を登録しました。');
    }

    public function edit(ReadingPlan $plan)
    {
        $this->authorize('update', $plan);

        return view('reading-plans.edit', ['readingPlan' => $plan->load('book')]);
    }

    public function update(ReadingPlanRequest $request, ReadingPlan $plan)
    {
        DB::transaction(function () use ($request, $plan) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $plan = ReadingPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $this->authorize('update', $plan);
            $this->checkDuplicate($request->user(), $plan->book_id, $plan->id);
            $plan->update(['target_date' => $request->validated('target_date'), 'status' => ReadingPlanStatus::InProgress]);
        });

        return redirect()->route('reading-plans.index')->with('success', '期日を更新しました。');
    }

    public function complete(ReadingPlan $plan)
    {
        $this->authorize('complete', $plan);
        DB::transaction(function () use ($plan) {
            $plan = ReadingPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($plan->status !== ReadingPlanStatus::Completed) {
                $plan->update(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()]);
            }
        });

        return redirect()->route('reading-plans.index')->with('success', '読了しました。');
    }

    public function destroy(ReadingPlan $plan)
    {
        $this->authorize('delete', $plan);
        DB::transaction(function () use ($plan) {
            $plan = ReadingPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $plan->user->notifications()->where('data->reading_plan_id', $plan->id)->delete();
            $plan->delete();
        });

        return redirect()->route('reading-plans.index')->with('success', '読書計画を削除しました。');
    }

    private function checkDuplicate(User $user, int $bookId, ?int $except = null): void
    {
        $query = $user->readingPlans()->where('book_id', $bookId)->where('status', ReadingPlanStatus::InProgress);
        if ($except) {
            $query->whereKeyNot($except);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([$except ? 'target_date' : 'book_id' => 'この書籍には読書中の計画がすでにあります。']);
        }
    }
}
