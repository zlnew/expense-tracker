<?php

use App\Mcp\McpServer;
use App\Models\Balance;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Auth::login($this->user);
    $this->balance = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Cash Wallet',
        'initial_amount' => 500000,
        'final_amount' => 500000,
        'is_primary' => true,
    ]);
    $this->category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Groceries',
        'type' => 'expense',
    ]);
});

test('mcp server handles initialize handshake', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
            'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
        ],
    ]);

    expect($response)->toBeArray()
        ->and($response['id'])->toBe(1)
        ->and($response['result']['serverInfo']['name'])->toBe('expense-tracker-mcp')
        ->and($response['result']['protocolVersion'])->toBe('2024-11-05')
        ->and($response['result']['capabilities'])->toHaveKeys(['tools', 'resources']);
});

test('mcp server handles ping', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'ping',
    ]);

    expect($response)->toBeArray()
        ->and($response['id'])->toBe(2)
        ->and($response['result'])->toBeObject();
});

test('mcp server lists all available tools', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/list',
    ]);

    expect($response['result']['tools'])->toBeArray()
        ->and(count($response['result']['tools']))->toBeGreaterThanOrEqual(10);

    $toolNames = collect($response['result']['tools'])->pluck('name')->all();

    expect($toolNames)->toContain('list_transactions')
        ->toContain('create_transaction')
        ->toContain('delete_transaction')
        ->toContain('get_balance_summary')
        ->toContain('get_budget_status')
        ->toContain('get_impending_drains')
        ->toContain('transfer_balance')
        ->toContain('pay_from_fund')
        ->toContain('create_fund_contribution')
        ->toContain('reconcile_balance')
        ->toContain('list_categories')
        ->toContain('list_funds')
        ->toContain('list_recurring_transactions');
});

test('mcp server creates a transaction and updates balance', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_transaction',
            'arguments' => [
                'amount' => 50000,
                'type' => 'expense',
                'category_id' => $this->category->id,
                'balance_id' => $this->balance->id,
                'description' => 'Test Coffee',
                'date' => now()->toDateString(),
            ],
        ],
    ]);

    expect($response['id'])->toBe(4)
        ->and($response['result']['content'][0]['text'])->toContain('Transaction #')
        ->and($response['result']['content'][0]['text'])->toContain('Test Coffee');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'amount' => 50000,
        'type' => 'expense',
        'description' => 'Test Coffee',
    ]);

    expect($this->balance->fresh()->final_amount)->toBe(450000);
});

test('mcp server lists transactions with filters', function () {
    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'amount' => 25000,
        'description' => 'Indomaret Snack',
        'date' => now()->toDateString(),
    ]);

    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => [
            'name' => 'list_transactions',
            'arguments' => [
                'search' => 'Indomaret',
            ],
        ],
    ]);

    expect($response['result']['content'][0]['text'])->toContain('Indomaret Snack');
});

test('mcp server returns balance summary with real and reserved legs', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 6,
        'method' => 'tools/call',
        'params' => [
            'name' => 'get_balance_summary',
            'arguments' => [],
        ],
    ]);

    expect($response['result']['content'][0]['text'])
        ->toContain('Cash Wallet')
        ->toContain('total_net_worth');
});

test('mcp server lists and reads resources', function () {
    $server = new McpServer($this->user);

    $listResponse = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'resources/list',
    ]);

    $uris = collect($listResponse['result']['resources'])->pluck('uri')->all();
    expect($uris)->toContain('expense-tracker://balances')
        ->toContain('expense-tracker://budget/active')
        ->toContain('expense-tracker://impending-drains')
        ->toContain('expense-tracker://categories');

    $readResponse = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 8,
        'method' => 'resources/read',
        'params' => [
            'uri' => 'expense-tracker://balances',
        ],
    ]);

    expect($readResponse['result']['contents'][0]['mimeType'])->toBe('application/json')
        ->and($readResponse['result']['contents'][0]['text'])->toContain('Cash Wallet');
});

test('mcp server transfers money between accounts', function () {
    $destBalance = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Bank BCA',
        'initial_amount' => 1000000,
        'final_amount' => 1000000,
    ]);

    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 9,
        'method' => 'tools/call',
        'params' => [
            'name' => 'transfer_balance',
            'arguments' => [
                'from_balance_id' => $this->balance->id,
                'to_balance_id' => $destBalance->id,
                'amount' => 100000,
                'description' => 'Deposit to BCA',
            ],
        ],
    ]);

    expect($response['result']['content'][0]['text'])->toContain('Successfully transferred');
    expect($this->balance->fresh()->final_amount)->toBe(400000);
    expect($destBalance->fresh()->final_amount)->toBe(1100000);
});

test('mcp server handles balance reconciliation', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 10,
        'method' => 'tools/call',
        'params' => [
            'name' => 'reconcile_balance',
            'arguments' => [
                'balance_id' => $this->balance->id,
                'actual_amount' => 500000,
            ],
        ],
    ]);

    expect($response['result']['content'][0]['text'])->toContain('reconciled');
    expect($this->balance->fresh()->reconciled_amount)->toBe(500000);
});

test('mcp server handles delete transaction', function () {
    $txn = Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'amount' => 50000,
        'type' => 'expense',
    ]);

    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 11,
        'method' => 'tools/call',
        'params' => [
            'name' => 'delete_transaction',
            'arguments' => [
                'transaction_id' => $txn->id,
            ],
        ],
    ]);

    expect($response['result']['content'][0]['text'])->toContain('deleted successfully');
    $this->assertSoftDeleted('transactions', ['id' => $txn->id]);
});

test('mcp server returns error on unknown tool or method', function () {
    $server = new McpServer($this->user);

    $unknownMethod = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 12,
        'method' => 'invalid/method',
    ]);

    expect($unknownMethod['error']['code'])->toBe(-32601);

    $unknownTool = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 13,
        'method' => 'tools/call',
        'params' => [
            'name' => 'non_existent_tool',
        ],
    ]);

    expect($unknownTool['error']['code'])->toBe(-32601);
});

test('POST /api/mcp endpoint works with sanctum authentication', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 100,
        'method' => 'tools/call',
        'params' => [
            'name' => 'list_categories',
            'arguments' => [],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('id', 100)
        ->assertJsonStructure([
            'jsonrpc',
            'id',
            'result' => [
                'content' => [
                    ['type', 'text'],
                ],
            ],
        ]);
});

test('artisan mcp:serve command executes single request via --request option', function () {
    Artisan::call('mcp:serve', [
        '--user' => $this->user->id,
        '--request' => json_encode([
            'jsonrpc' => '2.0',
            'id' => 200,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
            ],
        ]),
    ]);

    $output = Artisan::output();

    expect($output)->toContain('"jsonrpc":"2.0"')
        ->and($output)->toContain('expense-tracker-mcp');
});
