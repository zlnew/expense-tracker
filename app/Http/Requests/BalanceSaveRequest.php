<?php

namespace App\Http\Requests;

use App\DTO\BalanceData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\WithData;

class BalanceSaveRequest extends FormRequest
{
    /**
     * @use WithData<BalanceData>
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
            'description' => ['nullable', 'string'],
            'initial_amount' => ['required', 'integer'],
        ];
    }

    protected function dataClass(): string
    {
        return BalanceData::class;
    }
}
