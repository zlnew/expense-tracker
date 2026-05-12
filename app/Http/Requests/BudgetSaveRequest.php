<?php

namespace App\Http\Requests;

use App\DTO\BudgetData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return BudgetData::class;
    }
}
