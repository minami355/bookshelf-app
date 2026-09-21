<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_orders_by_average_then_review_count_excludes_unreviewed_and_limits_ten(): void
    {
        $high = Book::factory()->create(['title' => '最高評価']);
        $many = Book::factory()->create(['title' => '同率レビュー多数']);
        $few = Book::factory()->create(['title' => '同率レビュー少数']);
        $unreviewed = Book::factory()->create(['title' => 'レビューなし']);

        Review::factory()->for($high)->for(User::factory())->create(['rating' => 5]);
        Review::factory()->for($many)->for(User::factory())->create(['rating' => 4]);
        Review::factory()->for($many)->for(User::factory())->create(['rating' => 4]);
        Review::factory()->for($few)->for(User::factory())->create(['rating' => 4]);
        Book::factory()->count(9)->create()->each(function (Book $book): void {
            Review::factory()->for($book)->for(User::factory())->create(['rating' => 3]);
        });

        $this->get(route('ranking.index'))->assertOk()
            ->assertViewHas('rankedBooks', function ($books) use ($high, $many, $few, $unreviewed): bool {
                return $books->count() === 10
                    && $books[0]->is($high)
                    && $books[1]->is($many)
                    && $books[2]->is($few)
                    && ! $books->contains($unreviewed);
            });
    }

    public function test_guest_can_view_ranking(): void
    {
        $this->get(route('ranking.index'))->assertOk();
    }
}
