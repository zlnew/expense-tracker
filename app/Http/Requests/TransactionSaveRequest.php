<?php

namespace App\Http\Requests;

use App\DTO\TransactionData;
use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\WithData;

class TransactionSaveRequest extends FormRequest
{
    /**
     * @use WithData<TransactionData>
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
            'balance_id' => ['required', 'exists:balances,id'],
            'budget_id' => ['required', 'exists:budgets,id'],
            'budget_item_id' => ['required', 'exists:budget_items,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'type' => ['required', new Enum(CategoryType::class)],
            'date' => ['required', 'date'],
            'amount' => ['required', 'integer'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return TransactionData::class;
    }
}
