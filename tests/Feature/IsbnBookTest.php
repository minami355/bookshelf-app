<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsbnBookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_lookup_populates_fields_without_saving_and_user_can_edit_before_save(): void
    {
        config(['services.google_books.key' => 'test-key']);
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['volumeInfo' => [
            'title' => '取得タイトル', 'authors' => ['著者A', '著者B'],
            'publishedDate' => '2020-02-29', 'description' => '取得説明',
            'imageLinks' => ['thumbnail' => 'http://example.com/cover.jpg'],
        ]]]])]);
        $this->actingAs(User::factory()->create());
        $this->get('/books/create')->assertOk()->assertSee('isbn-search')->assertSee('fetch-btn');
        $data = $this->get('/books/isbn/9781234567890')->assertOk()->assertExactJson([
            'title' => '取得タイトル', 'author' => '著者A、著者B', 'published_date' => '2020-02-29',
            'description' => '取得説明', 'image_url' => 'https://example.com/cover.jpg',
        ])->json();
        $this->assertDatabaseCount('books', 0);
        Http::assertSent(fn ($request) => $request['q'] === 'isbn:9781234567890' && $request['key'] === 'test-key');
        $genre = Genre::factory()->create();
        $data['title'] = '編集済みタイトル';
        $this->post('/books', $data + ['isbn' => '9781234567890', 'genre_ids' => [$genre->id]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('books', ['title' => '編集済みタイトル', 'isbn' => '9781234567890']);
    }

    public function test_missing_fields_and_partial_dates_are_returned_as_null(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['2020', '2020-02', '2020-02-30', null] as $date) {
            Http::fake(['www.googleapis.com/*' => Http::response(['items' => [['volumeInfo' => [
                'title' => 'タイトルのみ', 'publishedDate' => $date,
            ]]]])]);
            $this->getJson('/books/isbn/9781234567890')->assertOk()
                ->assertJsonPath('published_date', null)->assertJsonPath('author', null)
                ->assertJsonPath('description', null)->assertJsonPath('image_url', null);
        }
        $this->assertDatabaseCount('books', 0);
    }

    public function test_invalid_isbn_returns_422_without_contacting_google(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());
        foreach (['123', '12345678901234', 'abcdefghijklm', '978-123456789', '１２３４５６７８９０１２３'] as $isbn) {
            $this->get('/books/isbn/'.rawurlencode($isbn))->assertUnprocessable()
                ->assertJsonPath('error', 'ISBNは13桁の数字で入力してください。');
        }
        Http::assertNothingSent();
    }

    public function test_no_results_returns_404(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['totalItems' => 0])]);
        $this->actingAs(User::factory()->create())->get('/books/isbn/9781234567890')
            ->assertNotFound()->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }

    public function test_upstream_errors_and_timeouts_return_502(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([403, 429, 500] as $status) {
            Http::fake(['www.googleapis.com/*' => Http::response([], $status)]);
            $this->get('/books/isbn/9781234567890')->assertStatus(502)->assertJsonStructure(['error']);
        }
        Http::fake(['www.googleapis.com/*' => Http::response('invalid json', 200)]);
        $this->get('/books/isbn/9781234567890')->assertStatus(502);
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->get('/books/isbn/9781234567890')->assertStatus(502);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_lookup_requires_login(): void
    {
        Http::fake();
        $this->get('/books/isbn/9781234567890')->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_web_registration_and_edit_allow_missing_isbn_and_publication_date(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $payload = ['title' => '手入力', 'author' => '著者', 'genre_ids' => [$genre->id]];
        $this->actingAs($user)->post('/books', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $book = Book::firstOrFail();
        $this->assertNull($book->isbn);
        $this->assertNull($book->published_date);
        $this->put('/books/'.$book->id, $payload + ['isbn' => null, 'published_date' => null])
            ->assertSessionHasNoErrors()->assertRedirect();
    }
}
