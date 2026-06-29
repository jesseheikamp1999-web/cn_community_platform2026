<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DiscordSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiscordSyncController extends Controller
{
    public function __invoke(Request $request, DiscordSyncService $sync): JsonResponse
    {
        $providedKey = (string) $request->header('x-api-key', '');
        $expectedKey = $sync->configuredApiKey();
        $channel = strtolower(trim((string) $request->query('channel', 'all')));
        $knownVersion = trim((string) $request->query('known_version', ''));
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
            $sync->recordRequest(false, 0, 'Ongeldige API-sleutel.', $providedKey, $channel, 401, $ipAddress, $userAgent);

            return response()->json([
                'success' => false,
                'message' => 'Ongeldige API-sleutel.',
            ], 401);
        }

        try {
            $payload = $sync->response($channel, $knownVersion !== '' ? $knownVersion : null);
            $statusCode = $payload['success'] ? 200 : 400;
            $itemCount = isset($payload['items']) && is_array($payload['items'])
                ? count($payload['items'])
                : (isset($payload['item']) ? 1 : 0);

            $sync->recordRequest(
                $payload['success'],
                $itemCount,
                $payload['success'] ? null : ($payload['message'] ?? 'Onbekende fout'),
                $providedKey,
                $channel,
                $statusCode,
                $ipAddress,
                $userAgent
            );

            return response()->json($payload, $statusCode);
        } catch (\Throwable $exception) {
            $sync->recordRequest(false, 0, Str::limit($exception->getMessage(), 1000), $providedKey, $channel, 500, $ipAddress, $userAgent);

            return response()->json([
                'success' => false,
                'message' => 'Discord sync kon niet worden gegenereerd.',
            ], 500);
        }
    }
}
