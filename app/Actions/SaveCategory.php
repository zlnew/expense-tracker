<?php

namespace App\Actions;

use App\DTO\CategoryData;
use App\Models\Category;

class SaveCategory extends Action
{
    public function __construct(
        private readonly Category $category,
        private readonly CategoryData $data,
    ) {}

    public function handle(): void
    {
        $this->category->fill([
            'type' => $this->data->type,
            'name' => $this->data->name,
        ]);

        $this->category->save();
    }
}
