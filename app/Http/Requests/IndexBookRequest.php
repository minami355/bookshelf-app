<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBookRequest extends FormRequest
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
     * 検証前に入力値を正規化する。
     *
     * @return void 戻り値なし
     */
    protected function prepareForValidation(): void
    {
        $sort = $this->input('sort');
        $this->merge(['sort' => in_array($sort, ['latest', 'oldest', 'title', 'rating'], true) ? $sort : 'latest']);
    }

    /**
     * 入力項目の検証ルールを返す。
     *
     * @return array 処理結果の配列
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre_id' => ['nullable', 'integer', Rule::exists('genres', 'id')],
            'sort' => ['required', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
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
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre_id.integer' => 'ジャンルIDは整数で指定してください。',
            'genre_id.exists' => '指定されたジャンルは存在しません。',
            'sort.required' => '並び順を指定してください。',
            'sort.string' => '並び順は文字列で指定してください。',
            'page.integer' => 'ページは整数で指定してください。',
            'page.min' => 'ページは1以上で指定してください。',
        ];
    }
}
