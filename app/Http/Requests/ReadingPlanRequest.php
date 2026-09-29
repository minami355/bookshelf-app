<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReadingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->route('plan') || $this->user()->can('update', $this->route('plan'));
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['status' => ['nullable', Rule::enum(ReadingPlanStatus::class)]];
        }
        $rules = ['target_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Tokyo')->toDateString()]];
        if ($this->isMethod('POST')) {
            $rules['book_id'] = ['required', 'integer', 'exists:books,id'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->isMethod('GET') || $validator->errors()->isNotEmpty()) {
                return;
            }
            $plan = $this->route('plan');
            $query = $this->user()->readingPlans()->where('book_id', $plan?->book_id ?? $this->input('book_id'))->where('status', ReadingPlanStatus::InProgress);
            if ($plan) {
                $query->whereKeyNot($plan->id);
            }
            if ($query->exists()) {
                $validator->errors()->add($plan ? 'target_date' : 'book_id', 'この書籍には読書中の計画がすでにあります。');
            }
        });
    }

    public function messages(): array
    {
        return [
            'book_id.required' => '書籍を選択してください。', 'book_id.integer' => '書籍を正しく選択してください。', 'book_id.exists' => '選択した書籍は存在しません。',
            'target_date.required' => '期日を入力してください。', 'target_date.date_format' => '期日は有効な日付で入力してください。', 'target_date.after_or_equal' => '期日は当日以降を指定してください。', 'status.Illuminate\Validation\Rules\Enum' => '状態を正しく選択してください。', 'status.enum' => '状態を正しく選択してください。',
        ];
    }
}
