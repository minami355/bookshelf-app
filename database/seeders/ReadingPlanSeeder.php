<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReadingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today('Asia/Tokyo');
        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();
        $suzuki = User::where('email', 'suzuki@example.com')->firstOrFail();
        $books = Book::orderBy('id')->take(6)->get();
        foreach ([3, 0, -3, 7, -10, 5] as $index => $offset) {
            ($index === 5 ? $suzuki : $yamada)->readingPlans()->create(['book_id' => $books[$index]->id, 'target_date' => $today->copy()->addDays($offset), 'status' => $index === 4 ? ReadingPlanStatus::Completed : ReadingPlanStatus::InProgress, 'completed_at' => $index === 4 ? $today->copy()->subDays(5) : null]);
        }
    }
}
