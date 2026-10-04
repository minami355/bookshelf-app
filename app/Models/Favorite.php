<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    use HasFactory;

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
