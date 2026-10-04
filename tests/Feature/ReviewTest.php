<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_update_and_delete_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 5,
            'comment' => 'とても良い本でした。',
        ])->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'レビューを投稿しました。');

        $review = Review::query()->whereBelongsTo($user)->whereBelongsTo($book)->firstOrFail();
        $this->actingAs($user)->get(route('reviews.edit', $review))->assertOk();
        $this->actingAs($user)->put(route('reviews.update', $review), [
            'rating' => 4,
            'comment' => '更新したコメントです。',
        ])->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'レビューを更新しました。');
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 4, 'comment' => '更新したコメントです。']);

        $this->actingAs($user)->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', 'レビューを削除しました。');
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_review_validation_checks_rating_comment_and_duplicate_submission(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->from(route('books.show', $book))->post(route('reviews.store', $book), [
            'rating' => 6,
            'comment' => str_repeat('あ', 1001),
        ])->assertSessionHasErrors(['rating', 'comment']);

        Review::factory()->for($user)->for($book)->create();
        $response = $this->actingAs($user)->from(route('books.show', $book))->post(route('reviews.store', $book), [
            'rating' => 3,
            'comment' => '二回目の投稿',
        ]);

        $response->assertSessionHasErrors('rating');
        $this->assertSame('この書籍にはすでにレビューを投稿しています。', session('errors')->first('rating'));
        $this->assertSame(1, Review::query()->whereBelongsTo($user)->whereBelongsTo($book)->count());
    }

    #[DataProvider('invalidReviewInputs')]
    public function test_invalid_review_cannot_be_created(array $input, string $field, string $message): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->from(route('books.show', $book))
            ->post(route('reviews.store', $book), array_replace([
                'rating' => 3,
                'comment' => '有効なコメントです。',
            ], $input))
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('reviews', 0);
    }

    #[DataProvider('invalidReviewInputs')]
    public function test_invalid_review_update_preserves_original_values(array $input, string $field, string $message): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create([
            'rating' => 4,
            'comment' => '更新前のコメントです。',
        ]);

        $this->actingAs($user)->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), array_replace([
                'rating' => 3,
                'comment' => '更新後のコメントです。',
            ], $input))
            ->assertRedirect(route('reviews.edit', $review))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => '更新前のコメントです。',
        ]);
    }

    public static function invalidReviewInputs(): array
    {
        return [
            'rating below minimum' => [['rating' => 0], 'rating', '評価は1から5の間で選択してください。'],
            'rating above maximum' => [['rating' => 6], 'rating', '評価は1から5の間で選択してください。'],
            'fractional rating' => [['rating' => 3.5], 'rating', '評価は整数で入力してください。'],
            'empty comment' => [['comment' => ''], 'comment', 'コメントは必須です。'],
            'comment above maximum length' => [['comment' => str_repeat('あ', 1001)], 'comment', 'コメントは1000文字以内で入力してください。'],
        ];
    }

    #[DataProvider('validBoundaryRatings')]
    public function test_review_can_be_created_and_updated_at_validation_boundaries(int $rating): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $comment = str_repeat('あ', 1000);

        $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => $rating,
            'comment' => $comment,
        ])->assertRedirect(route('books.show', $book))->assertSessionHasNoErrors();

        $review = Review::query()->whereBelongsTo($user)->whereBelongsTo($book)->firstOrFail();
        $this->assertSame($rating, (int) $review->rating);
        $this->assertSame($comment, $review->comment);

        $updatedComment = str_repeat('い', 1000);
        $updatedRating = $rating === 1 ? 5 : 1;

        $this->actingAs($user)->put(route('reviews.update', $review), [
            'rating' => $updatedRating,
            'comment' => $updatedComment,
        ])->assertRedirect(route('books.show', $book))->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertSame($updatedRating, (int) $review->rating);
        $this->assertSame($updatedComment, $review->comment);
    }

    public static function validBoundaryRatings(): array
    {
        return [
            'minimum rating' => [1],
            'maximum rating' => [5],
        ];
    }

    public function test_only_author_can_edit_update_or_delete_review(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $review = Review::factory()->for($author)->create();

        $this->actingAs($other)->get(route('reviews.edit', $review))->assertForbidden();
        $this->actingAs($other)->put(route('reviews.update', $review), ['rating' => 2, 'comment' => '変更'])->assertForbidden();
        $this->actingAs($other)->delete(route('reviews.destroy', $review))->assertForbidden();
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_deleting_review_also_deletes_its_likes(): void
    {
        $author = User::factory()->create();
        $liker = User::factory()->create();
        $review = Review::factory()->for($author)->create();
        ReviewLike::forceCreate(['user_id' => $liker->id, 'review_id' => $review->id]);

        $this->actingAs($author)->delete(route('reviews.destroy', $review));

        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }
}
