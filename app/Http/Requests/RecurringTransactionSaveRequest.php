<?php

namespace App\Http\Requests;

use App\DTO\RecurringTransactionData;
use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\WithData;

class RecurringTransactionSaveRequest extends FormRequest
{
    /**
     * @use WithData<RecurringTransactionData>
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
            'type' => ['required', new Enum(CategoryType::class)],
            'balance_id' => [
                'required',
                Rule::exists('balances', 'id')->where('user_id', $this->user()?->id),
            ],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'next_run_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function dataClass(): string
    {
        return RecurringTransactionData::class;
    }
}
