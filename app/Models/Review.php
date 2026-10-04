<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

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

    /**
     * 関連するいいねのリレーションを定義する。
     *
     * @return HasMany 関連データを取得するリレーション
     */
    public function likes(): HasMany
    {
        return $this->hasMany(ReviewLike::class);
    }

    /**
     * 関連するいいねしたユーザーのリレーションを定義する。
     *
     * @return BelongsToMany 関連データを取得するリレーション
     */
    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'review_likes',
            'review_id',
            'user_id'
        )->withTimestamps();
    }
}
