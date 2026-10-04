<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGenreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:genres,name'],
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
            'name.required' => 'ジャンル名は必須です。',
            'name.string' => 'ジャンル名は文字列で入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'このジャンル名は既に登録されています。',
        ];
    }
}
