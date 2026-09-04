<?php

namespace App\Mcp;

use App\Mcp\Resources\ActiveBudgetResource;
use App\Mcp\Resources\BalancesResource;
use App\Mcp\Resources\CategoriesResource;
use App\Mcp\Resources\ImpendingDrainsResource;
use App\Mcp\Resources\ResourceInterface;
use App\Mcp\Tools\CreateFundContributionTool;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\DeleteTransactionTool;
use App\Mcp\Tools\GetBalanceSummaryTool;
use App\Mcp\Tools\GetBudgetStatusTool;
use App\Mcp\Tools\GetImpendingDrainsTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\ListFundsTool;
use App\Mcp\Tools\ListRecurringTransactionsTool;
use App\Mcp\Tools\ListTransactionsTool;
use App\Mcp\Tools\PayFromFundTool;
use App\Mcp\Tools\ReconcileBalanceTool;
use App\Mcp\Tools\SyncFinancialIntegrityTool;
use App\Mcp\Tools\ToolInterface;
use App\Mcp\Tools\TransferBalanceTool;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class McpServer
{
    /** @var array<string, ToolInterface> */
    protected array $tools = [];

    /** @var array<string, ResourceInterface> */
    protected array $resources = [];

    public function __construct(
        protected ?User $user = null
    ) {
        $this->registerDefaults();
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function registerTool(ToolInterface $tool): self
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    public function registerResource(ResourceInterface $resource): self
    {
        $this->resources[$resource->uri()] = $resource;

        return $this;
    }

    protected function registerDefaults(): void
    {
        // 13 Tools
        $this->registerTool(new ListTransactionsTool);
        $this->registerTool(new CreateTransactionTool);
        $this->registerTool(new DeleteTransactionTool);
        $this->registerTool(new GetBalanceSummaryTool);
        $this->registerTool(new GetBudgetStatusTool);
        $this->registerTool(new GetImpendingDrainsTool);
        $this->registerTool(new TransferBalanceTool);
        $this->registerTool(new PayFromFundTool);
        $this->registerTool(new CreateFundContributionTool);
        $this->registerTool(new ReconcileBalanceTool);
        $this->registerTool(new SyncFinancialIntegrityTool);
        $this->registerTool(new ListCategoriesTool);
        $this->registerTool(new ListFundsTool);
        $this->registerTool(new ListRecurringTransactionsTool);

        // 4 Resources
        $this->registerResource(new BalancesResource);
        $this->registerResource(new ActiveBudgetResource);
        $this->registerResource(new ImpendingDrainsResource);
        $this->registerResource(new CategoriesResource);
    }

    /**
     * Handle incoming JSON-RPC 2.0 message array.
     * Returns response array or null (for notifications).
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>|null
     */
    public function handle(array $request): ?array
    {
        $id = $request['id'] ?? null;
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];

        // Notifications don't have an id, or explicitly match notifications/*
        $isNotification = ! array_key_exists('id', $request) || str_starts_with($method, 'notifications/');

        try {
            $result = match ($method) {
                'initialize' => $this->handleInitialize($params),
                'notifications/initialized' => null,
                'ping' => new \stdClass,
                'tools/list' => $this->handleToolsList(),
                'tools/call' => $this->handleToolsCall($params),
                'resources/list' => $this->handleResourcesList(),
                'resources/read' => $this->handleResourcesRead($params),
                default => throw new \InvalidArgumentException("Method not found: {$method}", -32601),
            };

            if ($isNotification) {
                return null;
            }

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ];
        } catch (\Throwable $e) {
            if ($isNotification) {
                return null;
            }

            $code = $e instanceof \InvalidArgumentException && $e->getCode() !== 0
                ? $e->getCode()
                : -32603;

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => $code,
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    protected function handleInitialize(array $params): array
    {
        $version = (string) ($params['protocolVersion'] ?? '2024-11-05');

        return [
            'protocolVersion' => $version,
            'capabilities' => [
                'tools' => new \stdClass,
                'resources' => new \stdClass,
            ],
            'serverInfo' => [
                'name' => 'expense-tracker-mcp',
                'version' => '1.0.0',
            ],
        ];
    }

    protected function handleToolsList(): array
    {
        $list = [];
        foreach ($this->tools as $tool) {
            $list[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'inputSchema' => $tool->schema(),
            ];
        }

        return ['tools' => $list];
    }

    protected function handleToolsCall(array $params): array
    {
        $name = $params['name'] ?? throw new \InvalidArgumentException('Missing tool name', -32602);
        $arguments = $params['arguments'] ?? [];

        $tool = $this->tools[$name] ?? throw new \InvalidArgumentException("Tool not found: {$name}", -32601);

        if (! $this->user) {
            throw new \RuntimeException('User not authenticated in MCP server context');
        }

        Auth::setUser($this->user);

        return $tool->execute($this->user, $arguments);
    }

    protected function handleResourcesList(): array
    {
        $list = [];
        foreach ($this->resources as $res) {
            $list[] = [
                'uri' => $res->uri(),
                'name' => $res->name(),
                'description' => $res->description(),
                'mimeType' => $res->mimeType(),
            ];
        }

        return ['resources' => $list];
    }

    protected function handleResourcesRead(array $params): array
    {
        $uri = $params['uri'] ?? throw new \InvalidArgumentException('Missing resource uri', -32602);
        $res = $this->resources[$uri] ?? throw new \InvalidArgumentException("Resource not found: {$uri}", -32602);

        if (! $this->user) {
            throw new \RuntimeException('User not authenticated in MCP server context');
        }

        return [
            'contents' => [
                [
                    'uri' => $uri,
                    'mimeType' => $res->mimeType(),
                    'text' => $res->read($this->user),
                ],
            ],
        ];
    }
}
