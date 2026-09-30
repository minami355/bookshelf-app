<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_matches_title_or_author_and_combines_with_genre(): void
    {
        $genre = Genre::factory()->create();
        $title = Book::factory()->create(['title' => '検索対象', 'author' => '別の著者']);
        $author = Book::factory()->create(['title' => '別の本', 'author' => '検索著者']);
        $excluded = Book::factory()->create(['title' => '検索対象だが別ジャンル']);
        $title->genres()->attach($genre);
        $author->genres()->attach($genre);
        $url = '/books?'.http_build_query(['keyword' => ' 検索 ', 'genre_id' => $genre->id, 'sort' => 'oldest']);
        $this->get($url)->assertOk()->assertViewHas('books', function ($books) use ($title, $author) {
            return $books->pluck('id')->sort()->values()->all() === collect([$title->id, $author->id])->sort()->values()->all();
        });
        $this->get('/books?keyword=')->assertOk()->assertViewHas('books', fn ($books) => $books->total() === 3);
        $this->get('/books?keyword=%20%20')->assertOk()->assertViewHas('books', fn ($books) => $books->total() === 3);
        $this->get('/books?keyword='.$title->isbn)->assertOk()->assertViewHas('books', fn ($books) => $books->isEmpty());
    }

    public function test_sort_orders_and_fallbacks_and_unreviewed_books_last(): void
    {
        $old = Book::factory()->create(['title' => 'B', 'created_at' => now()->subDays(3)]);
        $new = Book::factory()->create(['title' => 'A', 'created_at' => now()->subDays(2)]);
        $unrated = Book::factory()->create(['title' => 'C', 'created_at' => now()]);
        Review::factory()->for($old)->create(['rating' => 5]);
        Review::factory()->for($old)->create(['rating' => 3]);
        Review::factory()->for($new)->create(['rating' => 4]);
        foreach ([
            'latest' => [$unrated->id, $new->id, $old->id],
            'oldest' => [$old->id, $new->id, $unrated->id],
            'title' => [$new->id, $old->id, $unrated->id],
            'rating' => [$new->id, $old->id, $unrated->id],
            'invalid' => [$unrated->id, $new->id, $old->id],
            '' => [$unrated->id, $new->id, $old->id],
        ] as $sort => $ids) {
            $this->get('/books?sort='.$sort)->assertOk()
                ->assertViewHas('books', fn ($books) => $books->pluck('id')->all() === $ids);
        }
        $this->get('/')->assertOk()->assertSee('タイトル・著者で検索');
    }

    public function test_pagination_keeps_all_filters(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->count(11)->create(['title' => '検索対象'])->each(fn ($book) => $book->genres()->attach($genre));
        $this->get('/books?'.http_build_query(['keyword' => '検索', 'genre_id' => $genre->id, 'sort' => 'title']))
            ->assertOk()->assertViewHas('books', function ($books) use ($genre) {
                parse_str(parse_url($books->nextPageUrl(), PHP_URL_QUERY), $query);

                return $books->count() === 10 && $query === [
                    'keyword' => '検索', 'genre_id' => (string) $genre->id, 'sort' => 'title', 'page' => '2',
                ];
            });
    }

    public function test_invalid_genre_returns_to_previous_screen_with_errors(): void
    {
        $this->from('/books')->get('/books?genre_id=999999')
            ->assertRedirect('/books')->assertSessionHasErrors('genre_id');
        $this->get('/books')->assertOk()->assertSee('指定されたジャンルは存在しません。');
    }

    public function test_individual_filters_and_second_page_results(): void
    {
        $genre = Genre::factory()->create();
        $title = Book::factory()->create(['title' => '探すタイトル', 'author' => '著者A']);
        $author = Book::factory()->create(['title' => '別タイトル', 'author' => '探す著者']);
        $title->genres()->attach($genre);
        foreach ([['keyword' => '探すタイトル'], ['keyword' => '探す著者'], ['genre_id' => $genre->id], ['keyword' => '存在しない語']] as $i => $query) {
            $ids = [[$title->id], [$author->id], [$title->id], []][$i];
            $this->get('/books?'.http_build_query($query))->assertOk()
                ->assertViewHas('books', fn ($books) => $books->modelKeys() === $ids);
        }
        $books = Book::factory()->count(11)->sequence(fn ($sequence) => ['title' => sprintf('ページ対象%02d', $sequence->index)])->create();
        $books->each(fn ($book) => $book->genres()->attach($genre));
        $query = ['keyword' => 'ページ対象', 'genre_id' => $genre->id, 'sort' => 'title'];
        $first = $this->get('/books?'.http_build_query($query))->assertOk()->viewData('books');
        $second = $this->get($first->nextPageUrl())->assertOk()->viewData('books');
        $this->assertSame([$books->last()->id], $second->modelKeys());
        $this->assertSame(11, $second->total());
    }

    public function test_rating_sort_prioritizes_average_over_registration_date(): void
    {
        $high = Book::factory()->create(['created_at' => now()->subDays(2)]);
        $low = Book::factory()->create(['created_at' => now()]);
        Review::factory()->for($high)->create(['rating' => 5]);
        Review::factory()->for($low)->create(['rating' => 2]);
        $this->get('/books?sort=rating')->assertOk()
            ->assertViewHas('books', fn ($books) => $books->modelKeys() === [$high->id, $low->id]);
    }
}
