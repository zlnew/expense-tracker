<?php

namespace App\Console\Commands;

use App\Mcp\McpServer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class McpServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:serve {--user= : User ID or email to authenticate as (defaults to primary user)} {--request= : Single JSON-RPC request to execute and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start Model Context Protocol (MCP) server over standard input/output (stdio)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userOption = $this->option('user');

        $user = null;
        if ($userOption) {
            $user = is_numeric($userOption)
                ? User::query()->find($userOption)
                : User::query()->where('email', $userOption)->first();

            if (! $user) {
                fwrite(STDERR, "Error: User '{$userOption}' not found.\n");

                return self::FAILURE;
            }
        } else {
            $user = User::query()->first();
            if (! $user) {
                fwrite(STDERR, "Error: No users found in database.\n");

                return self::FAILURE;
            }
        }

        // Authenticate the user for all Actions, queries, and Auth::id() references
        Auth::login($user);

        $server = new McpServer($user);

        $requestOption = $this->option('request');
        if ($requestOption) {
            $req = json_decode($requestOption, true);
            if (! is_array($req)) {
                $err = [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error: invalid JSON',
                    ],
                ];
                $this->line(json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return self::FAILURE;
            }

            $response = $server->handle($req);
            if ($response !== null) {
                $this->line(json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            return self::SUCCESS;
        }

        // Notify client on stderr only (stdout must be pure JSON-RPC)
        fwrite(STDERR, "MCP Server initialized for user {$user->name} (#{$user->id}). Listening on stdio...\n");

        while (! feof(STDIN)) {
            $line = fgets(STDIN);
            if ($line === false) {
                break;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $request = json_decode($line, true);
            if (! is_array($request)) {
                $err = [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error: invalid JSON',
                    ],
                ];
                fwrite(STDOUT, json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                fflush(STDOUT);

                continue;
            }

            $response = $server->handle($request);
            if ($response !== null) {
                fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                fflush(STDOUT);
            }
        }

        return self::SUCCESS;
    }
}
