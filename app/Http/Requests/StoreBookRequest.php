<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'digits:13',
                Rule::unique('books', 'isbn'),
            ],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => [
                'integer',
                'distinct',
                Rule::exists('genres', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者名は必須です。',
            'author.string' => '著者名は文字列で入力してください。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'このISBNはすでに登録されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は正しい日付で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'image_url.url' => '画像URLは正しいURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルを1つ以上選択してください。',
            'genres.array' => 'ジャンルの入力形式が正しくありません。',
            'genres.min' => 'ジャンルを1つ以上選択してください。',
            'genres.*.integer' => 'ジャンルの入力形式が正しくありません。',
            'genres.*.distinct' => '同じジャンルが重複しています。',
            'genres.*.exists' => '選択されたジャンルは存在しません。',
        ];
    }
}
