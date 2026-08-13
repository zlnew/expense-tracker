<?php

namespace App\Http\Requests;

use App\DTO\FundData;
use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Spatie\LaravelData\WithData;

class FundSaveRequest extends FormRequest
{
    /**
     * @use WithData<FundData>
     */
    use WithData;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'integer', 'min:1'],
            'cadence' => ['required', new In(['cycle', 'monthly'])],
            'contribution_amount' => ['nullable', 'integer', 'min:1'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->where('type', CategoryType::EXPENSE->value),
            ],
            'next_due' => ['nullable', 'date'],
            'due_interval_months' => ['required', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return FundData::class;
    }
}
