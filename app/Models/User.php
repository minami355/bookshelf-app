<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * 関連する書籍のリレーションを定義する。
     *
     * @return HasMany 関連データを取得するリレーション
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
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
     * 関連するレビューへのいいねのリレーションを定義する。
     *
     * @return HasMany 関連データを取得するリレーション
     */
    public function reviewLikes(): HasMany
    {
        return $this->hasMany(ReviewLike::class);
    }

    /**
     * 関連するお気に入り書籍のリレーションを定義する。
     *
     * @return BelongsToMany 関連データを取得するリレーション
     */
    public function favoriteBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites')
            ->withTimestamps();
    }

    /**
     * 関連するいいねしたレビューのリレーションを定義する。
     *
     * @return BelongsToMany 関連データを取得するリレーション
     */
    public function likedReviews(): BelongsToMany
    {
        return $this->belongsToMany(
            Review::class,
            'review_likes',
            'user_id',
            'review_id'
        )->withTimestamps();
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
