<?php

namespace App\Http\Controllers\Integrations;

use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Services\Integrations\GoogleSheetSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Webhook for the Apps Script in the lead sheet (signed with the shared secret). */
class GoogleSheetController extends Controller
{
    public function __construct(private readonly GoogleSheetSyncService $sync) {}

    public function __invoke(Request $request): JsonResponse
    {
        $body = $request->getContent();
        try {
            $this->sync->verify($body, $request->header('X-Auxtech-Timestamp'), $request->header('X-Auxtech-Signature'));
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 401);
        }
        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'Body must be JSON.'], 422);
        }
        $summary = $this->sync->ingest($payload);

        return response()->json(['ok' => true] + $summary);
    }
}
