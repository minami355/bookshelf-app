<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, Genre $genre, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'title' => 'APIテスト書籍',
            'author' => 'API著者',
            'isbn' => '9781234567890',
            'published_date' => '2026-09-21',
            'description' => 'APIの説明文',
            'image_url' => 'https://example.com/api-book.jpg',
            'genre_ids' => [$genre->id],
        ], $overrides);
    }

    public function test_index_returns_paginated_resource_data_in_newest_order(): void
    {
        $genre = Genre::factory()->create();
        $old = Book::factory()->create(['title' => '古い書籍', 'created_at' => now()->subDay()]);
        $new = Book::factory()->create(['title' => '新しい書籍', 'created_at' => now()]);
        $old->genres()->attach($genre);
        $new->genres()->attach($genre);
        Review::factory()->for($new)->for(User::factory())->create(['rating' => 4]);
        Review::factory()->for($new)->for(User::factory())->create(['rating' => 5]);

        $response = $this->getJson('/api/v1/books');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'author', 'isbn', 'published_date', 'description', 'image_url', 'genres', 'average_rating', 'reviews_count', 'created_at', 'updated_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
            ])
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.0.average_rating', 4.5)
            ->assertJsonPath('data.0.reviews_count', 2)
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_index_supports_pagination_up_to_one_hundred(): void
    {
        Book::factory()->count(21)->create();

        $this->getJson('/api/v1/books?per_page=10&page=2')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 10);
        $this->getJson('/api/v1/books?per_page=100')->assertOk()->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/v1/books?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_index_searches_title_author_and_isbn_and_combines_genre_filter(): void
    {
        $targetGenre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();
        $titleBook = Book::factory()->create(['title' => '夏目の作品', 'author' => '別の著者', 'isbn' => '9780000000001']);
        $authorBook = Book::factory()->create(['title' => '別の作品', 'author' => '夏目漱石', 'isbn' => '9780000000002']);
        $isbnBook = Book::factory()->create(['title' => 'ISBN検索', 'author' => '著者', 'isbn' => '9781234567890']);
        $excluded = Book::factory()->create(['title' => '夏目だが別ジャンル']);
        $titleBook->genres()->attach($targetGenre);
        $authorBook->genres()->attach($targetGenre);
        $isbnBook->genres()->attach($targetGenre);
        $excluded->genres()->attach($otherGenre);

        $this->getJson('/api/v1/books?keyword='.urlencode(' 夏目 '))->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/books?keyword=9781234567890')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $isbnBook->id);
        $this->getJson("/api/v1/books?keyword=夏目&genre_id={$targetGenre->id}")
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_rejects_missing_genre_with_japanese_json_error(): void
    {
        $this->getJson('/api/v1/books?genre_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genre_id')
            ->assertJsonPath('errors.genre_id.0', '指定されたジャンルは存在しません。');
    }

    public function test_show_returns_genres_reviews_and_null_average_without_reviews(): void
    {
        $bookWithoutReviews = Book::factory()->create();
        $genre = Genre::factory()->create();
        $bookWithoutReviews->genres()->attach($genre);

        $this->getJson("/api/v1/books/{$bookWithoutReviews->id}")
            ->assertOk()
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.reviews_count', 0)
            ->assertJsonCount(0, 'data.reviews')
            ->assertJsonPath('data.genres.0.id', $genre->id);

        $reviewer = User::factory()->create(['name' => 'レビュー投稿者']);
        $review = Review::factory()->for($reviewer)->for($bookWithoutReviews)->create(['rating' => 5, 'comment' => 'おすすめです']);
        $this->getJson("/api/v1/books/{$bookWithoutReviews->id}")
            ->assertOk()
            ->assertJsonPath('data.reviews.0.id', $review->id)
            ->assertJsonPath('data.reviews.0.user_name', 'レビュー投稿者')
            ->assertJsonPath('data.average_rating', 5);
    }

    public function test_missing_book_returns_expected_json_and_404(): void
    {
        $this->getJson('/api/v1/books/999999')
            ->assertNotFound()
            ->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }

    public function test_store_update_and_delete_return_expected_status_and_sync_relations(): void
    {
        $owner = User::factory()->create();
        $attemptedNewOwner = User::factory()->create();
        $firstGenre = Genre::factory()->create();
        $secondGenre = Genre::factory()->create();

        $this->withToken($owner->createToken('test')->plainTextToken);
        $createResponse = $this->postJson('/api/v1/books', $this->payload($attemptedNewOwner, $firstGenre));
        $createResponse->assertCreated()->assertJsonPath('data.title', 'APIテスト書籍');
        $book = Book::query()->where('isbn', '9781234567890')->firstOrFail();
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $firstGenre->id]);

        $updateResponse = $this->putJson("/api/v1/books/{$book->id}", $this->payload($attemptedNewOwner, $secondGenre, [
            'title' => '更新後API書籍',
            'isbn' => $book->isbn,
        ]));
        $updateResponse->assertOk()->assertJsonPath('data.title', '更新後API書籍');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'user_id' => $owner->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $firstGenre->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $secondGenre->id]);

        $review = Review::factory()->for($book)->create();
        Favorite::forceCreate(['user_id' => $owner->id, 'book_id' => $book->id]);
        ReviewLike::forceCreate(['user_id' => $owner->id, 'review_id' => $review->id]);
        $this->deleteJson("/api/v1/books/{$book->id}")->assertNoContent();
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }

    public function test_store_and_update_validation_return_japanese_json_and_422(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($user)->create(['isbn' => '9781234567890']);
        $this->withToken($user->createToken('test')->plainTextToken);

        $this->postJson('/api/v1/books', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'author', 'genre_ids'])
            ->assertJsonPath('errors.title.0', 'タイトルは必須です。');

        $this->putJson("/api/v1/books/{$book->id}", $this->payload($user, $genre, ['isbn' => '123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn')
            ->assertJsonPath('errors.isbn.0', 'ISBNは13桁の数字で入力してください。');
    }
}
