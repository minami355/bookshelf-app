<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCrudTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $genre = Genre::factory()->create();

        return array_merge([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
            'published_date' => '2026-09-21',
            'description' => '説明文',
            'image_url' => 'https://example.com/book.jpg',
            'genre_ids' => [$genre->id],
        ], $overrides);
    }

    public function test_book_can_be_created_displayed_and_attached_to_genres(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload();

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $book = Book::query()->where('isbn', $payload['isbn'])->firstOrFail();
        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を登録しました。');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $payload['genre_ids'][0]]);
        $this->get(route('books.show', $book))->assertOk()->assertSee('テスト書籍');
    }

    public function test_book_input_is_validated_with_japanese_messages(): void
    {
        $user = User::factory()->create();
        Book::factory()->create(['isbn' => '9781234567890']);

        $response = $this->actingAs($user)->from(route('books.create'))->post(route('books.store'), [
            'title' => '',
            'author' => '',
            'isbn' => '9781234567890',
            'published_date' => 'invalid',
            'genre_ids' => [],
        ]);

        $response->assertRedirect(route('books.create'))
            ->assertSessionHasErrors(['title', 'author', 'isbn', 'published_date', 'genre_ids']);
        $this->assertSame('タイトルは必須です。', session('errors')->first('title'));
        $this->assertSame('このISBNはすでに登録されています。', session('errors')->first('isbn'));
    }

    public function test_owner_can_update_book_without_isbn_conflicting_with_itself(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['isbn' => '9781234567890']);
        $oldGenre = Genre::factory()->create();
        $newGenre = Genre::factory()->create();
        $book->genres()->attach($oldGenre);
        $payload = $this->payload([
            'title' => '更新後タイトル',
            'isbn' => $book->isbn,
            'genre_ids' => [$newGenre->id],
        ]);

        $this->actingAs($user)->put(route('books.update', $book), $payload)
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を更新しました。');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新後タイトル']);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $oldGenre->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $newGenre->id]);
    }

    public function test_only_owner_can_edit_update_or_delete_book(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create()->refresh();

        $this->actingAs($other)->get(route('books.edit', $book))->assertForbidden();
        $this->actingAs($other)->put(route('books.update', $book), $this->payload())->assertForbidden();
        $this->actingAs($other)->delete(route('books.destroy', $book))->assertForbidden();
        $this->assertSame($book->getRawOriginal(), $book->fresh()->getRawOriginal());
    }

    public function test_owner_can_delete_book_and_related_records_are_deleted(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $book = Book::factory()->for($owner)->create()->refresh();
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre);
        $review = Review::factory()->for($reviewer)->for($book)->create();
        Favorite::forceCreate(['user_id' => $reviewer->id, 'book_id' => $book->id]);
        ReviewLike::forceCreate(['user_id' => $owner->id, 'review_id' => $review->id]);

        $this->actingAs($owner)->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('success', '書籍を削除しました。');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }
}
