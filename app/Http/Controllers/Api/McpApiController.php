<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class McpApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $sessionId = (string) ($request->header('Mcp-Session-Id') ?: Str::uuid()->toString());

        if ($request->isMethod('GET')) {
            return response()->json([
                'name' => 'expense-tracker-mcp',
                'version' => '1.0.0',
                'protocolVersion' => '2024-11-05',
                'transport' => 'http',
            ])->header('Mcp-Session-Id', $sessionId);
        }

        $payload = $request->json()->all();

        if (empty($payload)) {
            $raw = json_decode($request->getContent(), true);
            if (is_array($raw)) {
                $payload = $raw;
            }
        }

        if (empty($payload) || ! is_array($payload)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error: invalid JSON',
                ],
            ], 200)->header('Mcp-Session-Id', $sessionId);
        }

        $user = $request->user();
        $server = new McpServer($user);

        // Handle JSON-RPC 2.0 batch requests
        if (array_is_list($payload)) {
            $responses = [];
            foreach ($payload as $item) {
                if (is_array($item)) {
                    $res = $server->handle($item);
                    if ($res !== null) {
                        $responses[] = $res;
                    }
                }
            }

            if (empty($responses)) {
                return response()->noContent()->header('Mcp-Session-Id', $sessionId);
            }

            return response()->json($responses)->header('Mcp-Session-Id', $sessionId);
        }

        $response = $server->handle($payload);

        if ($response === null) {
            return response()->noContent()->header('Mcp-Session-Id', $sessionId);
        }

        return response()->json($response)->header('Mcp-Session-Id', $sessionId);
    }
}
