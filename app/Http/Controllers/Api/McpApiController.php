<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if (empty($payload) || ! is_array($payload)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error: invalid JSON',
                ],
            ]);
        }

        $user = $request->user();
        $server = new McpServer($user);

        $response = $server->handle($payload);

        if ($response === null) {
            return response()->json(null, 204);
        }

        return response()->json($response);
    }
}
