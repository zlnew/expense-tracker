<?php

namespace App\Http\Requests;

use App\DTO\TransferBetweenAccountsData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\WithData;

class TransferBetweenAccountsRequest extends FormRequest
{
    /**
     * @use WithData<TransferBetweenAccountsData>
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
            'from_account_id' => ['required', 'exists:balances,id'],
            'to_account_id' => ['required', 'exists:balances,id'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'integer'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return TransferBetweenAccountsData::class;
    }
}
