<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_genre_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('genres.store'), ['name' => '小説'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを登録しました。');
        $genre = Genre::query()->where('name', '小説')->firstOrFail();

        $this->actingAs($user)->put(route('genres.update', $genre), ['name' => '文学'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '文学']);

        $this->actingAs($user)->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');
        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    public function test_genre_name_is_required_unique_and_allows_itself_on_update(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '小説']);

        $this->actingAs($user)->post(route('genres.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('genres.store'), ['name' => '小説'])
            ->assertSessionHasErrors('name');
        $this->actingAs($user)->put(route('genres.update', $genre), ['name' => '小説'])
            ->assertSessionHasNoErrors();
    }

    public function test_genre_detail_only_displays_related_books_and_paginates_ten_each(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();
        $related = Book::factory()->count(11)->for($user)->create();
        $unrelated = Book::factory()->for($user)->create(['title' => '表示されない書籍']);
        $related->each(fn (Book $book) => $book->genres()->attach($genre));
        $unrelated->genres()->attach($otherGenre);

        $response = $this->actingAs($user)->get(route('genres.show', $genre));

        $response->assertOk()->assertViewHas('books', function ($books): bool {
            return $books->total() === 11 && $books->perPage() === 10 && $books->count() === 10;
        })->assertDontSee('表示されない書籍');
    }

    public function test_genre_linked_to_book_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $book->genres()->attach($genre);

        $this->actingAs($user)->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error', 'このジャンルには書籍が紐づいているため削除できません。');
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
