<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ApiTokenCommand extends Command
{
    protected $signature = 'api:token {user} {--name=api} {--abilities=transactions:read,transactions:write,categories:read,balances:read}';

    protected $description = 'Create a Sanctum personal access token for a user (by id or email)';

    public function handle(): int
    {
        $identifier = $this->argument('user');

        // pgsql casts the id column to bigint, so a bare `where('id', $email)`
        // throws before the OR email clause is ever evaluated. Resolve by type.
        $user = ctype_digit((string) $identifier)
            ? User::find((int) $identifier)
            : User::where('email', $identifier)->first();

        if (! $user) {
            $this->error("No user found for '{$this->argument('user')}'.");

            return self::FAILURE;
        }

        $name = $this->option('name');
        $abilities = array_values(array_filter(array_map('trim', explode(',', $this->option('abilities')))));

        $token = $user->createToken($name, $abilities);

        $this->info("Token created for {$user->name} (id {$user->id})");
        $this->line("  ID:        {$token->accessToken->id}");
        $this->line("  Name:      {$name}");
        $this->line('  Abilities: '.implode(', ', $abilities));
        $this->line('  Token:     '.$token->plainTextToken);
        $this->newLine();
        $this->warn('The plaintext token is shown once and cannot be recovered later. Store it somewhere safe.');

        return self::SUCCESS;
    }
}
