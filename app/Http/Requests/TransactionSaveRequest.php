<?php

namespace App\Http\Requests;

use App\DTO\TransactionData;
use App\Enums\CategoryType;
use App\Models\Budget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'balance_id' => [
                'required',
                Rule::exists('balances', 'id')->where('user_id', $this->user()?->id),
            ],
            'budget_id' => [
                'nullable',
                Rule::exists('budgets', 'id')->where('user_id', $this->user()?->id),
            ],
            'budget_item_id' => [
                'nullable',
                Rule::exists('budget_items', 'id')
                    ->whereIn('budget_id', Budget::where('user_id', $this->user()?->id)->pluck('id')),
            ],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],
            'type' => ['required', new Enum(CategoryType::class)],
            'date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'transfer_group_id' => ['nullable', 'string', 'max:36'],
        ];
    }

    protected function dataClass(): string
    {
        return TransactionData::class;
    }
}
