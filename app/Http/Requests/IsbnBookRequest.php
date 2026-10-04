<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IsbnBookRequest extends FormRequest
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
        $this->merge(['isbn' => $this->route('isbn')]);
    }

    /**
     * 入力項目の検証ルールを返す。
     *
     * @return array 処理結果の配列
     */
    public function rules(): array
    {
        return ['isbn' => ['required', 'string', 'regex:/\A[0-9]{13}\z/']];
    }

    /**
     * 日本語の検証エラーメッセージを返す。
     *
     * @return array 処理結果の配列
     */
    public function messages(): array
    {
        return [
            'isbn.required' => 'ISBNを入力してください。',
            'isbn.string' => 'ISBNは13桁の数字で入力してください。',
            'isbn.regex' => 'ISBNは13桁の数字で入力してください。',
        ];
    }

    /**
     * 入力検証の失敗をエラーレスポンスとして返す。
     *
     * @param  Validator  $validator  追加の検証を適用するバリデーター
     * @return void 戻り値なし
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first('isbn'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
