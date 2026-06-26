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
        $expectedKey = (string) config('services.discord_sync.api_key', '');

        if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
            $sync->recordRequest(false, 0, 'Ongeldige API-sleutel.', $providedKey);

            return response()->json([
                'success' => false,
                'message' => 'Ongeldige API-sleutel.',
            ], 401);
        }

        try {
            $items = $sync->items();
            $sync->recordRequest(true, count($items), null, $providedKey);

            return response()->json([
                'success' => true,
                'items' => $items,
            ]);
        } catch (\Throwable $exception) {
            $sync->recordRequest(false, 0, Str::limit($exception->getMessage(), 1000), $providedKey);

            return response()->json([
                'success' => false,
                'message' => 'Discord sync kon niet worden gegenereerd.',
            ], 500);
        }
    }
}
