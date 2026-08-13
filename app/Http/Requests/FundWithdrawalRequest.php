<?php

namespace App\Http\Requests;

use App\DTO\FundContributionData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\WithData;

class FundWithdrawalRequest extends FormRequest
{
    /**
     * @use WithData<FundContributionData>
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
            'amount' => ['required', 'integer', 'min:1'],
            'balance_id' => [
                'required',
                Rule::exists('balances', 'id')->where('user_id', $this->user()?->id),
            ],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return FundContributionData::class;
    }
}
