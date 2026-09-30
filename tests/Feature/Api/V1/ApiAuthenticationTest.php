<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_can_be_issued_used_and_revoked_without_accept_header(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $otherToken = $user->createToken('other device');

        $token = $this->post('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'test device',
        ])->assertOk()->assertJsonStructure(['token'])->json('token');

        $this->withToken($token)->delete('/api/v1/tokens/current')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);

        // Each HTTP request resolves authentication again, as it does in production.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->post('/api/v1/books', [])->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken->plainTextToken)->post('/api/v1/books', [])
            ->assertUnprocessable();
    }

    public function test_invalid_credentials_return_401_and_missing_input_returns_422(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        foreach ([$user->email, 'missing@example.com'] as $email) {
            $this->post('/api/v1/tokens', [
                'email' => $email, 'password' => 'wrong', 'device_name' => 'test',
            ])->assertUnauthorized()->assertExactJson(['message' => '認証情報が正しくありません。']);
        }
        $this->post('/api/v1/tokens', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_all_write_endpoints_require_authentication_without_accept_header(): void
    {
        $book = Book::factory()->create();
        foreach ([
            ['POST', '/api/v1/books'],
            ['PUT', "/api/v1/books/{$book->id}"],
            ['DELETE', "/api/v1/books/{$book->id}"],
            ['PUT', '/api/v1/books/999999'],
            ['DELETE', '/api/v1/tokens/current'],
        ] as [$method, $uri]) {
            $this->{strtolower($method)}($uri)->assertUnauthorized()
                ->assertHeader('Content-Type', 'application/json');
        }
    }

    public function test_non_owner_is_rejected_before_validation_and_cannot_delete(): void
    {
        $book = Book::factory()->create();
        $this->withToken(User::factory()->create()->createToken('test')->plainTextToken);

        $this->put("/api/v1/books/{$book->id}", [])->assertForbidden()
            ->assertHeader('Content-Type', 'application/json');
        $this->delete("/api/v1/books/{$book->id}")->assertForbidden()
            ->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => $book->title]);
    }

    public function test_missing_book_has_the_same_json_for_get_put_and_delete(): void
    {
        $this->withToken(User::factory()->create()->createToken('test')->plainTextToken);
        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            $this->{strtolower($method)}('/api/v1/books/999999')->assertNotFound()
                ->assertExactJson(['error' => '書籍が見つかりませんでした。']);
        }
    }

    public function test_optional_fields_can_be_omitted_on_create_and_cleared_on_update(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $this->withToken($user->createToken('test')->plainTextToken);
        $payload = ['title' => '書籍', 'author' => '著者', 'genre_ids' => [$genre->id]];

        $id = $this->post('/api/v1/books', $payload)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('books', [
            'id' => $id, 'user_id' => $user->id, 'isbn' => null, 'published_date' => null,
        ]);
        Book::findOrFail($id)->update([
            'isbn' => '9781234567890', 'published_date' => '2026-09-28',
            'description' => '説明', 'image_url' => 'https://example.com/book.jpg',
        ]);
        $this->put("/api/v1/books/{$id}", $payload + [
            'isbn' => null, 'published_date' => null, 'description' => null, 'image_url' => null,
            'user_id' => 999999,
        ])->assertOk()->assertJsonPath('data.isbn', null)->assertJsonPath('data.published_date', null);
        $this->assertDatabaseHas('books', [
            'id' => $id, 'user_id' => $user->id, 'description' => null, 'image_url' => null,
        ]);
    }

    public function test_invalid_optional_fields_and_duplicate_genres_return_422(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $genre = Genre::factory()->create();
        $this->withToken($user->createToken('test')->plainTextToken);
        $payload = [
            'title' => '書籍', 'author' => '著者', 'isbn' => $book->isbn,
            'published_date' => 'invalid', 'image_url' => 'invalid',
            'genre_ids' => [$genre->id, $genre->id],
        ];
        $this->post('/api/v1/books', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['isbn', 'published_date', 'image_url', 'genre_ids.0']);
        $this->put("/api/v1/books/{$book->id}", $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['published_date', 'image_url', 'genre_ids.0'])
            ->assertJsonMissingValidationErrors('isbn');
    }

    public function test_issued_bearer_token_allows_crud_then_revocation_denies_access(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $genre = Genre::factory()->create();
        $token = $this->postJson('/api/v1/tokens', [
            'email' => $user->email, 'password' => 'password123', 'device_name' => 'integration',
        ])->assertOk()->json('token');
        $this->withToken($token);
        $payload = ['title' => '作成書籍', 'author' => '著者', 'genre_ids' => [$genre->id]];
        $id = $this->postJson('/api/v1/books', $payload)->assertCreated()
            ->assertJsonPath('data.title', '作成書籍')->assertJsonPath('data.author', '著者')
            ->assertJsonPath('data.genres.0.id', $genre->id)->json('data.id');
        $this->assertDatabaseHas('books', ['id' => $id, 'title' => '作成書籍', 'user_id' => $user->id]);
        $payload['title'] = '更新書籍';
        $this->putJson('/api/v1/books/'.$id, $payload)->assertOk()->assertJsonPath('data.title', '更新書籍');
        $this->assertDatabaseHas('books', ['id' => $id, 'title' => '更新書籍']);
        $this->deleteJson('/api/v1/books/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('books', ['id' => $id]);
        $this->deleteJson('/api/v1/tokens/current')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/books', $payload)->assertUnauthorized();
        $this->assertDatabaseCount('books', 0);
    }
}
