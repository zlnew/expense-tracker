<?php

use App\Mcp\McpServer;
use App\Models\Balance;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\SinkingFund;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Maulana']);

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
            'clientInfo' => ['name' => 'hermes-client', 'version' => '1.0.0'],
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

test('mcp server lists all available tools with valid input schemas', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/list',
    ]);

    $tools = $response['result']['tools'];
    expect($tools)->toBeArray()
        ->and(count($tools))->toBe(13);

    $toolNames = collect($tools)->pluck('name')->all();

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

    foreach ($tools as $t) {
        expect($t)->toHaveKeys(['name', 'description', 'inputSchema'])
            ->and($t['inputSchema']['type'])->toBe('object');
    }
});

test('mcp server creates an expense transaction and updates balance and budget', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_transaction',
            'arguments' => [
                'amount' => 75000,
                'type' => 'expense',
                'category_id' => $this->category->id,
                'balance_id' => $this->balance->id,
                'description' => 'Groceries at Superindo',
                'date' => now()->toDateString(),
            ],
        ],
    ]);

    expect($response['id'])->toBe(4)
        ->and($response['result']['content'][0]['text'])->toContain('Transaction #')
        ->and($response['result']['content'][0]['text'])->toContain('Groceries at Superindo');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'amount' => 75000,
        'type' => 'expense',
        'description' => 'Groceries at Superindo',
    ]);

    expect($this->balance->fresh()->final_amount)->toBe(425000);
});

test('mcp server rejects transaction creation with invalid amount or missing foreign entities', function () {
    $server = new McpServer($this->user);

    // Amount <= 0
    $res1 = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_transaction',
            'arguments' => [
                'amount' => 0,
                'type' => 'expense',
                'category_id' => $this->category->id,
                'balance_id' => $this->balance->id,
            ],
        ],
    ]);
    expect($res1['result']['isError'])->toBeTrue()
        ->and($res1['result']['content'][0]['text'])->toContain('Amount must be greater than zero');

    // Invalid category
    $res2 = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 6,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_transaction',
            'arguments' => [
                'amount' => 10000,
                'type' => 'expense',
                'category_id' => 999999,
                'balance_id' => $this->balance->id,
            ],
        ],
    ]);
    expect($res2['result']['isError'])->toBeTrue()
        ->and($res2['result']['content'][0]['text'])->toContain('Category with ID 999999 not found');
});

test('mcp server lists transactions with filters and pagination limits', function () {
    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'amount' => 25000,
        'description' => 'Indomaret Snack',
        'date' => now()->toDateString(),
    ]);
    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'amount' => 50000,
        'description' => 'Apotek Kimia Farma',
        'date' => now()->toDateString(),
    ]);

    $server = new McpServer($this->user);

    $resFiltered = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'tools/call',
        'params' => [
            'name' => 'list_transactions',
            'arguments' => ['search' => 'Indomaret'],
        ],
    ]);

    expect($resFiltered['result']['content'][0]['text'])
        ->toContain('Indomaret Snack')
        ->not->toContain('Kimia Farma');

    $resLimit = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 8,
        'method' => 'tools/call',
        'params' => [
            'name' => 'list_transactions',
            'arguments' => ['limit' => 1],
        ],
    ]);
    expect($resLimit['result']['content'][0]['text'])->toContain('Found 1 transactions');
});

test('mcp server gets balance summary with active, reserved, real, and net worth', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 9,
        'method' => 'tools/call',
        'params' => [
            'name' => 'get_balance_summary',
            'arguments' => [],
        ],
    ]);

    expect($response['result']['content'][0]['text'])
        ->toContain('Cash Wallet')
        ->toContain('total_net_worth')
        ->toContain('Rp 500.000');
});

test('mcp server gets budget status for active budget or notifies if none', function () {
    $server = new McpServer($this->user);

    // Case 1: No active budget
    $resNoBudget = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 10,
        'method' => 'tools/call',
        'params' => [
            'name' => 'get_budget_status',
            'arguments' => [],
        ],
    ]);
    expect($resNoBudget['result']['content'][0]['text'])->toContain('No active budget found');

    // Case 2: Active budget with items
    $budget = Budget::factory()->create([
        'user_id' => $this->user->id,
        'is_active' => true,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
    ]);
    BudgetItem::factory()->create([
        'budget_id' => $budget->id,
        'category_id' => $this->category->id,
        'planned_amount' => 1000000,
    ]);

    $resWithBudget = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 11,
        'method' => 'tools/call',
        'params' => [
            'name' => 'get_budget_status',
            'arguments' => [],
        ],
    ]);
    expect($resWithBudget['result']['content'][0]['text'])
        ->toContain('Budget Status')
        ->toContain('Rp 1.000.000');
});

test('mcp server gets impending drains forecast', function () {
    $server = new McpServer($this->user);

    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 12,
        'method' => 'tools/call',
        'params' => [
            'name' => 'get_impending_drains',
            'arguments' => ['horizon_days' => 30],
        ],
    ]);

    expect($response['result']['content'][0]['text'])
        ->toContain('Impending Cash Outflows')
        ->toContain('Total Outflow:');
});

test('mcp server transfers money between accounts and validates limits', function () {
    $destBalance = Balance::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Bank BCA',
        'initial_amount' => 1000000,
        'final_amount' => 1000000,
    ]);

    $server = new McpServer($this->user);

    // Happy path
    $response = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 13,
        'method' => 'tools/call',
        'params' => [
            'name' => 'transfer_balance',
            'arguments' => [
                'from_balance_id' => $this->balance->id,
                'to_balance_id' => $destBalance->id,
                'amount' => 100000,
                'description' => 'Cash to Bank',
            ],
        ],
    ]);

    expect($response['result']['content'][0]['text'])->toContain('Successfully transferred');
    expect($this->balance->fresh()->final_amount)->toBe(400000);
    expect($destBalance->fresh()->final_amount)->toBe(1100000);

    // Error: Same account
    $resSame = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 14,
        'method' => 'tools/call',
        'params' => [
            'name' => 'transfer_balance',
            'arguments' => [
                'from_balance_id' => $this->balance->id,
                'to_balance_id' => $this->balance->id,
                'amount' => 50000,
            ],
        ],
    ]);
    expect($resSame['result']['isError'])->toBeTrue()
        ->and($resSame['result']['content'][0]['text'])->toContain('cannot be the same');

    // Error: Insufficient funds
    $resOver = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 15,
        'method' => 'tools/call',
        'params' => [
            'name' => 'transfer_balance',
            'arguments' => [
                'from_balance_id' => $this->balance->id,
                'to_balance_id' => $destBalance->id,
                'amount' => 99999999,
            ],
        ],
    ]);
    expect($resOver['result']['isError'])->toBeTrue()
        ->and($resOver['result']['content'][0]['text'])->toContain('Insufficient funds');
});

test('mcp server contributes to sinking fund and pays out bill from fund', function () {
    $fund = SinkingFund::factory()->create([
        'user_id' => $this->user->id,
        'from_balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'name' => 'Emergency Fund',
        'target_amount' => 1000000,
        'next_due' => now()->addMonth()->toDateString(),
    ]);

    $server = new McpServer($this->user);

    // Step 1: Contribute to fund
    $contribRes = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 16,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_fund_contribution',
            'arguments' => [
                'fund_id' => $fund->id,
                'amount' => 300000,
                'description' => 'Monthly saving',
            ],
        ],
    ]);

    expect($contribRes['result']['content'][0]['text'])->toContain('Successfully contributed Rp 300.000');

    // Step 2: Pay from fund
    $payRes = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 17,
        'method' => 'tools/call',
        'params' => [
            'name' => 'pay_from_fund',
            'arguments' => [
                'fund_id' => $fund->id,
                'amount' => 150000,
                'description' => 'Medical bill payout',
            ],
        ],
    ]);

    expect($payRes['result']['content'][0]['text'])->toContain('Successfully paid Rp 150.000 from fund');

    // Step 3: Attempt paying more than remaining reserve (150k left) -> should fail
    $overPayRes = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 18,
        'method' => 'tools/call',
        'params' => [
            'name' => 'pay_from_fund',
            'arguments' => [
                'fund_id' => $fund->id,
                'amount' => 500000,
                'description' => 'Overdraft attempt',
            ],
        ],
    ]);

    expect($overPayRes['result']['isError'])->toBeTrue();
});

test('mcp server reconciles account and flags drift', function () {
    $server = new McpServer($this->user);

    // Exact match (500k recorded, 500k actual)
    $resExact = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 19,
        'method' => 'tools/call',
        'params' => [
            'name' => 'reconcile_balance',
            'arguments' => [
                'balance_id' => $this->balance->id,
                'actual_amount' => 500000,
            ],
        ],
    ]);
    expect($resExact['result']['content'][0]['text'])->toContain('Perfectly reconciled! Zero drift.');

    // Discrepancy (450k actual vs 500k recorded)
    $resDrift = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 20,
        'method' => 'tools/call',
        'params' => [
            'name' => 'reconcile_balance',
            'arguments' => [
                'balance_id' => $this->balance->id,
                'actual_amount' => 450000,
            ],
        ],
    ]);
    expect($resDrift['result']['content'][0]['text'])->toContain('Discrepancy: Real balance is Rp 50.000 LOWER');
});

test('mcp server deletes transaction and soft-deletes row', function () {
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
        'id' => 21,
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

test('mcp server lists categories and recurring transactions and funds', function () {
    $server = new McpServer($this->user);

    // Categories
    $resCat = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 22,
        'method' => 'tools/call',
        'params' => ['name' => 'list_categories', 'arguments' => []],
    ]);
    expect($resCat['result']['content'][0]['text'])->toContain('Groceries');

    // Funds
    SinkingFund::factory()->create([
        'user_id' => $this->user->id,
        'from_balance_id' => $this->balance->id,
        'name' => 'Vacation Fund',
        'target_amount' => 5000000,
    ]);
    $resFunds = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 23,
        'method' => 'tools/call',
        'params' => ['name' => 'list_funds', 'arguments' => []],
    ]);
    expect($resFunds['result']['content'][0]['text'])->toContain('Vacation Fund');

    // Recurring
    RecurringTransaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'description' => 'Wifi Monthly',
    ]);
    $resRec = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 24,
        'method' => 'tools/call',
        'params' => ['name' => 'list_recurring_transactions', 'arguments' => []],
    ]);
    expect($resRec['result']['content'][0]['text'])->toContain('Wifi Monthly');
});

test('mcp server lists and reads all 4 resources', function () {
    $server = new McpServer($this->user);

    $listRes = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 25,
        'method' => 'resources/list',
    ]);
    $uris = collect($listRes['result']['resources'])->pluck('uri')->all();
    expect($uris)->toHaveCount(4)
        ->toContain('expense-tracker://balances')
        ->toContain('expense-tracker://budget/active')
        ->toContain('expense-tracker://impending-drains')
        ->toContain('expense-tracker://categories');

    // Read balances
    $readBal = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 26,
        'method' => 'resources/read',
        'params' => ['uri' => 'expense-tracker://balances'],
    ]);
    expect($readBal['result']['contents'][0]['text'])->toContain('Cash Wallet');

    // Read categories
    $readCat = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 27,
        'method' => 'resources/read',
        'params' => ['uri' => 'expense-tracker://categories'],
    ]);
    expect($readCat['result']['contents'][0]['text'])->toContain('Groceries');

    // Read impending drains
    $readDrains = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 28,
        'method' => 'resources/read',
        'params' => ['uri' => 'expense-tracker://impending-drains'],
    ]);
    expect($readDrains['result']['contents'][0]['text'])->toContain('total_impending_outflow');
});

test('multi-tenant security: user B cannot view or tamper with user A data via MCP', function () {
    $userB = User::factory()->create(['name' => 'Other User']);
    $serverB = new McpServer($userB);

    // User A's transaction
    $txnA = Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $this->balance->id,
        'category_id' => $this->category->id,
        'amount' => 50000,
        'description' => 'User A Secret Transaction',
    ]);

    // 1. User B lists transactions -> sees empty, no User A records
    $listRes = $serverB->handle([
        'jsonrpc' => '2.0',
        'id' => 29,
        'method' => 'tools/call',
        'params' => ['name' => 'list_transactions', 'arguments' => []],
    ]);
    expect($listRes['result']['content'][0]['text'])
        ->not->toContain('User A Secret Transaction')
        ->toContain('Found 0 transactions');

    // 2. User B tries deleting User A's transaction -> rejected
    $delRes = $serverB->handle([
        'jsonrpc' => '2.0',
        'id' => 30,
        'method' => 'tools/call',
        'params' => [
            'name' => 'delete_transaction',
            'arguments' => ['transaction_id' => $txnA->id],
        ],
    ]);
    expect($delRes['result']['isError'])->toBeTrue()
        ->and($delRes['result']['content'][0]['text'])->toContain('not found or does not belong to user');
    expect($txnA->fresh()->deleted_at)->toBeNull();

    // 3. User B tries creating transaction into User A's balance -> rejected
    $createRes = $serverB->handle([
        'jsonrpc' => '2.0',
        'id' => 31,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_transaction',
            'arguments' => [
                'amount' => 10000,
                'type' => 'expense',
                'category_id' => $this->category->id,
                'balance_id' => $this->balance->id,
            ],
        ],
    ]);
    expect($createRes['result']['isError'])->toBeTrue();
});

test('mcp server returns standard error codes for unknown methods and tools', function () {
    $server = new McpServer($this->user);

    $unknownMethod = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 32,
        'method' => 'invalid/method',
    ]);
    expect($unknownMethod['error']['code'])->toBe(-32601);

    $unknownTool = $server->handle([
        'jsonrpc' => '2.0',
        'id' => 33,
        'method' => 'tools/call',
        'params' => ['name' => 'ghost_tool'],
    ]);
    expect($unknownTool['error']['code'])->toBe(-32601);
});

test('POST /api/mcp endpoint requires sanctum auth and handles tools, notifications, and errors', function () {
    // 1. Unauthenticated -> 401
    $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 34,
        'method' => 'ping',
    ])->assertUnauthorized();

    // 2. Authenticated -> 200
    Sanctum::actingAs($this->user);

    $resTool = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 35,
        'method' => 'tools/call',
        'params' => [
            'name' => 'list_categories',
            'arguments' => [],
        ],
    ]);
    $resTool->assertOk()
        ->assertJsonPath('id', 35)
        ->assertJsonStructure(['jsonrpc', 'id', 'result' => ['content']]);

    // 3. Notification -> 204 No Content
    $resNotif = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ]);
    $resNotif->assertNoContent();

    // 4. Malformed JSON / empty body -> 200 with JSON-RPC error -32700
    $resErr = $this->call('POST', '/api/mcp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], '{bad json');
    $resErr->assertOk()
        ->assertJsonPath('error.code', -32700);
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
