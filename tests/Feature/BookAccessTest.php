<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_public_pages(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.index'))->assertOk();
        $this->get(route('books.show', $book))->assertOk();
        $this->get(route('ranking.index'))->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_book_index_paginates_ten_books_per_page(): void
    {
        Book::factory()->count(11)->create();

        $this->get(route('books.index'))->assertOk()
            ->assertViewHas('books', fn ($books): bool => $books->total() === 11
                && $books->perPage() === 10
                && $books->count() === 10);
    }

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.create'))->assertRedirect('/login');
        $this->get(route('books.edit', $book))->assertRedirect('/login');
        $this->get(route('favorites.index'))->assertRedirect('/login');
        $this->get(route('genres.index'))->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_protected_pages(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)->get(route('books.create'))->assertOk();
        $this->actingAs($user)->get(route('books.edit', $book))->assertOk();
        $this->actingAs($user)->get(route('favorites.index'))->assertOk();
        $this->actingAs($user)->get(route('genres.index'))->assertOk();
    }

    public function test_missing_book_returns_not_found(): void
    {
        $this->get('/books/999999')->assertNotFound();
    }
}
