<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
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
     * 関連するジャンルのリレーションを定義する。
     *
     * @return BelongsToMany 関連データを取得するリレーション
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'book_genre')
            ->withTimestamps();
    }

    /**
     * 関連するレビューのリレーションを定義する。
     *
     * @return HasMany 関連データを取得するリレーション
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * 関連するお気に入りのリレーションを定義する。
     *
     * @return HasMany 関連データを取得するリレーション
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * 関連する読書計画のリレーションを定義する。
     *
     * @return HasMany 関連データを取得するリレーション
     */
    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }
}
