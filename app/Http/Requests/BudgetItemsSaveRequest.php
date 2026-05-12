<?php

namespace App\Http\Requests;

use App\DTO\BudgetItemsData;
use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\WithData;

class BudgetItemsSaveRequest extends FormRequest
{
    /**
     * @use WithData<BudgetItemsData>
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
            'items.*.category_id' => ['required', 'exists:categories,id'],
            'items.*.type' => ['required', new Enum(CategoryType::class)],
            'items.*.planned_amount' => ['required', 'integer'],
        ];
    }

    protected function dataClass(): string
    {
        return BudgetItemsData::class;
    }
}
