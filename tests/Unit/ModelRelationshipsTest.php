<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_return_their_expected_relationships(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);
        $review = Review::factory()->for($user)->for($book)->create();
        $favorite = Favorite::forceCreate(['user_id' => $user->id, 'book_id' => $book->id]);
        $like = ReviewLike::forceCreate(['user_id' => $user->id, 'review_id' => $review->id]);

        $this->assertTrue($user->books->contains($book));
        $this->assertTrue($user->reviews->contains($review));
        $this->assertTrue($user->favoriteBooks->contains($book));
        $this->assertTrue($user->likedReviews->contains($review));
        $this->assertTrue($book->user->is($user));
        $this->assertTrue($book->genres->contains($genre));
        $this->assertTrue($book->reviews->contains($review));
        $this->assertTrue($book->favorites->contains($favorite));
        $this->assertTrue($genre->books->contains($book));
        $this->assertTrue($review->user->is($user));
        $this->assertTrue($review->book->is($book));
        $this->assertTrue($review->likes->contains($like));
        $this->assertTrue($review->likedByUsers->contains($user));
        $this->assertTrue($favorite->user->is($user));
        $this->assertTrue($favorite->book->is($book));
        $this->assertTrue($like->user->is($user));
        $this->assertTrue($like->review->is($review));
    }
}
