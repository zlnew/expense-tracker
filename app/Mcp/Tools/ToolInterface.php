<?php

namespace App\Mcp\Tools;

use App\Models\User;

interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema object defining input arguments.
     *
     * @return array<string, mixed>
     */
    public function schema(): array;

    /**
     * Execute the tool with validated arguments.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    public function execute(User $user, array $arguments): array;
}
