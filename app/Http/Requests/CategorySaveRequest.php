<?php

namespace App\Http\Requests;

use App\DTO\CategoryData;
use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\WithData;

class CategorySaveRequest extends FormRequest
{
    /**
     * @use WithData<CategoryData>
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
            'type' => ['required', new Enum(CategoryType::class)],
            'name' => ['required', 'string'],
        ];
    }

    protected function dataClass(): string
    {
        return CategoryData::class;
    }
}
