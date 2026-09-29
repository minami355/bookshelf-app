<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IsbnBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['isbn' => $this->route('isbn')]);
    }

    public function rules(): array
    {
        return ['isbn' => ['required', 'string', 'regex:/\A[0-9]{13}\z/']];
    }

    public function messages(): array
    {
        return [
            'isbn.required' => 'ISBNを入力してください。',
            'isbn.string' => 'ISBNは13桁の数字で入力してください。',
            'isbn.regex' => 'ISBNは13桁の数字で入力してください。',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first('isbn'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
