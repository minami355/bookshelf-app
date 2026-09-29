<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/reports')->assertRedirect(route('login'));
    }

    public function test_empty_report_has_zero_counts_null_average_and_empty_rankings(): void
    {
        Review::factory()->create(['rating' => 5]);
        $response = $this->actingAs(User::factory()->create())->get('/reports')->assertOk();
        $stats = $response->viewData('stats');
        $this->assertSame(['total_reviews' => 0, 'books_read' => 0, 'average_rating' => null], $stats['summary']);
        $this->assertSame([0, 0, 0, 0, 0], $stats['rating_distribution']->all());
        $this->assertCount(0, $stats['top_rated_books']);
        $this->assertCount(0, $stats['genre_ratings']);
        $response->assertSee('4星以上の書籍がありません')
            ->assertSee('ジャンルが設定された書籍のレビューがありません');
    }

    public function test_report_uses_only_own_all_time_reviews_and_counts_each_genre(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();
        $book = Book::factory()->create();
        $book->genres()->attach($genres->modelKeys());
        Review::factory()->for($user)->for($book)->create(['rating' => 5, 'created_at' => '2020-01-01 12:00:00']);
        Review::factory()->for($book)->create(['rating' => 1]);
        foreach ([2, 3] as $rating) {
            Review::factory()->for($user)->create(['rating' => $rating]);
        }
        // Owning a book alone does not count as reading it.
        Book::factory()->for($user)->create();
        $response = $this->actingAs($user)->get('/reports')->assertOk();
        $stats = $response->viewData('stats');
        $this->assertSame(['total_reviews' => 3, 'books_read' => 3, 'average_rating' => 3.3], $stats['summary']);
        $this->assertSame([0, 1, 1, 0, 1], $stats['rating_distribution']->all());
        $this->assertSame([$book->id], $stats['top_rated_books']->pluck('id')->all());
        $this->assertCount(2, $stats['genre_ratings']);
        foreach ($stats['genre_ratings'] as $genre) {
            $this->assertEquals(5, $genre['average_rating']);
            $this->assertEquals(1, $genre['count']);
            $response->assertSee(route('genres.show', $genre['id']));
        }
        $response->assertSee(route('books.show', $book));
    }

    public function test_top_books_use_rating_then_review_date_and_limit_five(): void
    {
        $user = User::factory()->create();
        $expected = [];
        foreach ([5, 5, 4, 4, 4, 4, 3] as $index => $rating) {
            $review = Review::factory()->for($user)->create([
                'rating' => $rating,
                'created_at' => '2026-01-'.sprintf('%02d', $index + 1).' 12:00:00',
            ]);
            $expected[] = $review->book_id;
        }
        Review::factory()->create(['rating' => 5, 'created_at' => '2026-09-29 12:00:00']);
        $stats = $this->actingAs($user)->get('/reports')->assertOk()->viewData('stats');
        $this->assertSame([$expected[1], $expected[0], $expected[5], $expected[4], $expected[3]], $stats['top_rated_books']->pluck('id')->all());
    }

    public function test_genres_use_average_then_count_and_limit_five(): void
    {
        $user = User::factory()->create();
        $ids = [];
        foreach ([[5], [4], [4, 4], [3], [2], [1]] as $ratings) {
            $genre = Genre::factory()->create();
            $ids[] = $genre->id;
            foreach ($ratings as $rating) {
                $review = Review::factory()->for($user)->create(['rating' => $rating]);
                $review->book->genres()->attach($genre);
            }
        }
        $stats = $this->actingAs($user)->get('/reports')->assertOk()->viewData('stats');
        $this->assertSame([$ids[0], $ids[2], $ids[1], $ids[3], $ids[4]], $stats['genre_ratings']->pluck('id')->all());
        $this->assertEquals(2, $stats['genre_ratings'][1]['count']);
    }
}
