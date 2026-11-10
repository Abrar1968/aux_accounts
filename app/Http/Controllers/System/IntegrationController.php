<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\Integrations\GoogleSheetSyncService;
use Symfony\Component\HttpFoundation\Response;

/** Settings → Integrations: enable / rotate the Google Sheets secret and download the Apps Script. */
class IntegrationController extends Controller
{
    public function __construct(private readonly GoogleSheetSyncService $sync) {}

    public function rotate()
    {
        $first = $this->sync->secret() === null;
        $this->sync->rotateSecret();

        return $this->toast($first ? 'Google Sheets sync enabled. Download the script and install it in the sheet.' : 'Secret rotated. Download the new script and replace the old one in the sheet — the old secret no longer works.', 'success', $first ? 'Enabled' : 'Secret rotated');
    }

    public function script(): Response
    {
        return response($this->sync->appsScript(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="AuxtechLeadSync.gs"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
