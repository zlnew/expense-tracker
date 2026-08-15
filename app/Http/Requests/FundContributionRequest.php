<?php

namespace App\Http\Requests;

use App\DTO\FundContributionData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\WithData;

class FundContributionRequest extends FormRequest
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
            // Future-dated set-asides must not inflate today's reserve.
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return FundContributionData::class;
    }
}
