<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        // 各書籍2〜4件、合計32件
        $reviewCounts = [2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3];

        $comments = [
            '物語の展開が面白く、最後まで楽しめました。',
            '内容が分かりやすく、実生活でも役立つ一冊でした。',
            '新しい知識を得られて、とても勉強になりました。',
            '具体例が豊富で、内容を理解しやすかったです。',
            '何度も読み返したくなる、おすすめの一冊です。',
        ];

        foreach ($books as $bookIndex => $book) {
            $reviewCount = $reviewCounts[$bookIndex];

            for ($reviewIndex = 0; $reviewIndex < $reviewCount; $reviewIndex++) {
                $user = $users[
                    ($bookIndex + $reviewIndex) % $users->count()
                ];

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => 3 + (($bookIndex + $reviewIndex) % 3),
                    'comment' => $comments[
                        ($bookIndex + $reviewIndex) % count($comments)
                    ],
                ]);
            }
        }
    }
}
