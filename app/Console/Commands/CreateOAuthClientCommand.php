<?php

namespace App\Console\Commands;

use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateOAuthClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oauth:client {name : The client display name} {redirect_uri : The client callback/redirect URI} {--user= : User ID to own this client}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new OAuth 2.0 client for connected apps like Google Gemini';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $redirectUri = (string) $this->argument('redirect_uri');
        $userId = $this->option('user');

        $user = $userId ? User::find($userId) : User::first();

        if (! $user) {
            $this->error('No user found to own this OAuth client.');

            return 1;
        }

        $clientId = Str::random(32);
        $clientSecret = Str::random(64);

        $client = OAuthClient::create([
            'id' => $clientId,
            'user_id' => $user->id,
            'name' => $name,
            'secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
        ]);

        $this->info('OAuth 2.0 client created successfully!');
        $this->newLine();
        $this->table(
            ['Key', 'Value'],
            [
                ['Client Name', $client->name],
                ['Client ID', $client->id],
                ['Client Secret', $clientSecret],
                ['Redirect URI', $client->redirect_uri],
                ['Owner', "{$user->name} ({$user->email})"],
            ]
        );
        $this->newLine();
        $this->comment('Keep the Client Secret safe. It will not be displayed again.');

        return 0;
    }
}
