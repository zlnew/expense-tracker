<?php

namespace App\Mcp\Resources;

use App\Models\User;

interface ResourceInterface
{
    public function uri(): string;

    public function name(): string;

    public function description(): string;

    public function mimeType(): string;

    public function read(User $user): string;
}
