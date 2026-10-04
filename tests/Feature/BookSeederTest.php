<?php

namespace Tests\Feature;

use App\Models\Book;
use Database\Seeders\BookSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_finds_isbn_independently_of_random_owner(): void
    {
        $this->seed([UserSeeder::class, GenreSeeder::class, BookSeeder::class]);
        $owners = Book::pluck('user_id', 'id')->all();
        $this->seed(BookSeeder::class);
        $this->assertDatabaseCount('books', 11);
        $this->assertSame($owners, Book::pluck('user_id', 'id')->all());
        $this->assertTrue(Book::withCount('genres')->get()->every(fn (Book $book): bool => $book->genres_count > 0));
    }
}
