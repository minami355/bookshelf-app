<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleBooksService
{
    /**
     * Google Booksから書籍情報を取得し、利用可能な値を返す。
     *
     * @param  string  $isbn  検索対象の13桁ISBN
     * @return ?array 書籍情報、見つからなければnull
     */
    public function findByIsbn(string $isbn): ?array
    {
        $query = ['q' => 'isbn:'.$isbn, 'maxResults' => 1];
        if ($key = config('services.google_books.key')) {
            $query['key'] = $key;
        }

        $response = Http::acceptJson()
            ->connectTimeout(config('services.google_books.connect_timeout'))
            ->timeout(config('services.google_books.timeout'))
            ->get(config('services.google_books.url'), $query);
        $response->throw();

        $body = $response->json();
        if (! is_array($body) || isset($body['error'])) {
            throw new RuntimeException('Invalid Google Books response.');
        }
        if (($body['totalItems'] ?? null) === 0 || ($body['items'] ?? null) === []) {
            return null;
        }
        $info = $body['items'][0]['volumeInfo'] ?? null;
        if (! is_array($info)) {
            throw new RuntimeException('Missing Google Books volume information.');
        }

        $authors = array_filter((array) ($info['authors'] ?? []), fn (mixed $author): bool => is_string($author));
        $image = $this->text($info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null);

        return [
            'title' => $this->text($info['title'] ?? null),
            'author' => $authors ? implode('、', $authors) : null,
            'published_date' => $this->date($info['publishedDate'] ?? null),
            'description' => $this->text($info['description'] ?? null),
            'image_url' => $image && filter_var($image, FILTER_VALIDATE_URL)
                ? preg_replace('/^http:/', 'https:', $image) : null,
        ];
    }

    /**
     * 空でない文字列を返し、それ以外はnullにする。
     *
     * @param  mixed  $value  正規化する外部APIの値
     * @return ?string 有効な文字列、該当しなければnull
     */
    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * 年月日が揃った有効な日付だけを返す。
     *
     * @param  mixed  $value  正規化する外部APIの値
     * @return ?string 有効な文字列、該当しなければnull
     */
    private function date(mixed $value): ?string
    {
        // Do not invent a month or day when Google only supplies a year/month.
        if (! is_string($value) || ! preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts)) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }
}
