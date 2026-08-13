<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Models\Budget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * PATCH /api/transactions/{id} — partial update of a transaction.
 *
 * Same user-scoped foreign-key rules as the create request, but every field
 * is optional (`sometimes`) so a caller can fix just budget_id /
 * budget_item_id / category_id without resubmitting the whole row.
 */
class TransactionUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'balance_id' => [
                'sometimes',
                Rule::exists('balances', 'id')->where('user_id', $this->user()?->id),
            ],
            'budget_id' => [
                'sometimes',
                'nullable',
                Rule::exists('budgets', 'id')->where('user_id', $this->user()?->id),
            ],
            'budget_item_id' => [
                'sometimes',
                'nullable',
                Rule::exists('budget_items', 'id')
                    ->whereIn('budget_id', Budget::where('user_id', $this->user()?->id)->pluck('id')),
            ],
            'category_id' => [
                'sometimes',
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],
            'type' => ['sometimes', new Enum(CategoryType::class)],
            'date' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string'],
            'transfer_group_id' => ['sometimes', 'nullable', 'string', 'max:36'],
        ];
    }
}
