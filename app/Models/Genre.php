<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * 関連する書籍のリレーションを定義する。
     *
     * @return BelongsToMany 関連データを取得するリレーション
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_genre')
            ->withTimestamps();
    }
}
