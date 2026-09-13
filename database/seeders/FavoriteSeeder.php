<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $bookIds = Book::pluck('id');

        User::all()->each(function (User $user) use ($bookIds): void {
            $selectedBookIds = $bookIds
                ->random(random_int(3, 5))
                ->all();

            $user->favoriteBooks()
                ->syncWithoutDetaching($selectedBookIds);
        });
    }
}
