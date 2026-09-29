<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    protected $fillable = ['book_id', 'target_date', 'status', 'completed_at'];

    protected $casts = ['target_date' => 'date', 'completed_at' => 'datetime', 'status' => ReadingPlanStatus::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
