<?php

namespace App\Http\Requests;

use App\DTO\TransactionsData;
use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\WithData;

class TransactionsSaveRequest extends FormRequest
{
    /**
     * @use WithData<TransactionsData>
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
            'items.*.budget_id' => ['required', 'exists:budgets,id'],
            'items.*.budget_item_id' => ['required', 'exists:budget_items,id'],
            'items.*.category_id' => ['required', 'exists:categories,id'],
            'items.*.type' => ['required', new Enum(CategoryType::class)],
            'items.*.date' => ['required', 'date'],
            'items.*.amount' => ['required', 'integer'],
            'items.*.description' => ['nullable', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return TransactionsData::class;
    }
}
