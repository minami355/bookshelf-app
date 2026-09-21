<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteAndLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorite_can_be_added_removed_and_added_again_without_duplicates(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('favorites.toggle', $book))->assertRedirect();
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);
        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);
        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->assertSame(1, Favorite::query()->whereBelongsTo($user)->whereBelongsTo($book)->count());
    }

    public function test_favorites_page_only_contains_logged_in_users_books_and_paginates(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $books = Book::factory()->count(11)->create();
        $otherBook = Book::factory()->create(['title' => '他人のお気に入り']);
        $books->each(fn (Book $book) => Favorite::forceCreate(['user_id' => $user->id, 'book_id' => $book->id]));
        Favorite::forceCreate(['user_id' => $other->id, 'book_id' => $otherBook->id]);

        $this->actingAs($user)->get(route('favorites.index'))
            ->assertOk()
            ->assertViewHas('books', fn ($paginator): bool => $paginator->total() === 11 && $paginator->perPage() === 10)
            ->assertDontSee('他人のお気に入り');
    }

    public function test_review_like_can_be_added_removed_and_added_again_without_duplicates(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $this->actingAs($user)->post(route('reviews.like', $review))->assertRedirect();
        $this->assertDatabaseHas('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);
        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertDatabaseMissing('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);
        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertSame(1, ReviewLike::query()->whereBelongsTo($user)->whereBelongsTo($review)->count());
    }

    public function test_guest_cannot_toggle_favorite_or_review_like(): void
    {
        $book = Book::factory()->create();
        $review = Review::factory()->for($book)->create();

        $this->post(route('favorites.toggle', $book))->assertRedirect('/login');
        $this->post(route('reviews.like', $review))->assertRedirect('/login');
    }
}
