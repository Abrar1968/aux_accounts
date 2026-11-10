<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\UpdateSettingsRequest;
use App\Services\Crm\CrmSettingsService;
use App\Services\Integrations\GoogleSheetSyncService;
use App\Services\Shared\SettingsService;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(CrmSettingsService $crm): Response
    {
        return Inertia::render('System/Settings', [
            'settings' => array_diff_key($this->settings->all(), array_flip([GoogleSheetSyncService::SECRET_KEY, GoogleSheetSyncService::STATUS_KEY])),
            'sheets' => fn () => ['enabled' => app(GoogleSheetSyncService::class)->secret() !== null, 'endpoint' => route('integrations.sheets.leads'), 'status' => app(GoogleSheetSyncService::class)->status()], 'editable' => SettingsService::EDITABLE, 'crm' => $crm->all(),
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $changed = $this->settings->updateMany($request->validated('settings'));

        return $this->toast(count($changed).' setting(s) updated. Statements recalculate automatically.');
    }
}
