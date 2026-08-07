<?php

namespace App\Http\Requests;

use App\DTO\TransferBetweenAccountsData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'from_account_id' => [
                'required',
                Rule::exists('balances', 'id')->where('user_id', $this->user()?->id),
            ],
            'to_account_id' => [
                'required',
                Rule::exists('balances', 'id')->where('user_id', $this->user()?->id),
            ],
            'date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return TransferBetweenAccountsData::class;
    }
}
