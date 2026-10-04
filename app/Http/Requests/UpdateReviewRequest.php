<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
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
