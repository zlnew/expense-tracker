<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * PATCH /api/funds/{id} — partial update of a fund.
 *
 * Every field is optional (`sometimes`) so a caller can tweak one property
 * without resubmitting the whole row. Same user-scoped category rule as the
 * create request.
 */
class FundUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'target_amount' => ['sometimes', 'integer', 'min:1'],
            'cadence' => ['sometimes', new In(['cycle', 'monthly'])],
            'contribution_amount' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'category_id' => [
                'sometimes',
                'required',
                Rule::exists('categories', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->where('type', CategoryType::EXPENSE->value),
            ],
            'next_due' => ['sometimes', 'nullable', 'date'],
            'due_interval_months' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
