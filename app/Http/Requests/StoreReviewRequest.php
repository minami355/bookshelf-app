<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    /**
     * リクエストの実行権限を判定する。
     *
     * @return bool 許可または条件成立ならtrue
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 入力項目の検証ルールを返す。
     *
     * @return array 処理結果の配列
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * 標準ルールに加えて重複などの整合性を検証する。
     *
     * @param  Validator  $validator  追加の検証を適用するバリデーター
     * @return void 戻り値なし
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (Validator $validator): void {
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

    /**
     * 日本語の検証エラーメッセージを返す。
     *
     * @return array 処理結果の配列
     */
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
