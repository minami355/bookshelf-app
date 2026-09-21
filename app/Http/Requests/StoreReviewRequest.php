<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(
        \Illuminate\Validation\Validator $validator
    ): void {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $alreadyReviewed = $this->route('book')
                ->reviews()
                ->where('user_id', $this->user()->id)
                ->exists();

            if ($alreadyReviewed) {
                $validator->errors()->add(
                    'rating',
                    'この書籍にはすでにレビューを投稿しています。'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'rating.required' => '評価は必須です。',
            'rating.integer' => '評価は整数で入力してください。',
            'rating.between' => '評価は1から5の間で選択してください。',
            'comment.required' => 'コメントは必須です。',
            'comment.string' => 'コメントは文字列で入力してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ];
    }
}
