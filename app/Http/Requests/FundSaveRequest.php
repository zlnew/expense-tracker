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
            // Category is REQUIRED — the user picks where fund payouts
            // charge. Auto-created Maintenance/Taxes categories were a design
            // failure; the fund must have an explicit user-picked expense
            // category or payouts would mint orphan (uncategorized) expenses.
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->where('type', CategoryType::EXPENSE->value),
            ],
            'next_due' => ['nullable', 'date'],
            'from_balance_id' => ['required', Rule::exists('balances', 'id')->where('user_id', $this->user()?->id)],
            'due_interval_months' => ['required', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return FundData::class;
    }
}
