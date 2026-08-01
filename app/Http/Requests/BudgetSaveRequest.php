<?php

namespace App\Http\Requests;

use App\DTO\BudgetData;
use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\WithData;

class BudgetSaveRequest extends FormRequest
{
    /**
     * @use WithData<BudgetData>
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
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'cutoff_day' => ['required', 'integer'],
            'notes' => ['nullable', 'string'],
            'items.*.category_id' => [
                'required',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],
            'items.*.type' => ['required', new Enum(CategoryType::class)],
            'items.*.planned_amount' => ['required', 'integer'],
        ];
    }

    protected function dataClass(): string
    {
        return BudgetData::class;
    }
}
