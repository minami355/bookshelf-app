<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    protected $fillable = ['book_id', 'target_date', 'status', 'completed_at'];

    protected $casts = ['target_date' => 'date', 'completed_at' => 'datetime', 'status' => ReadingPlanStatus::class];

    /**
     * 関連するユーザーのリレーションを定義する。
     *
     * @return BelongsTo 関連データを取得するリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 関連する書籍のリレーションを定義する。
     *
     * @return BelongsTo 関連データを取得するリレーション
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
