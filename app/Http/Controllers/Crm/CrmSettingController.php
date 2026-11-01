<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\DailyTargetRequest;
use App\Http\Requests\Crm\SaveOptionRequest;
use App\Http\Requests\Crm\SaveOutcomeRequest;
use App\Services\Crm\CrmSettingsService;
use App\Services\Crm\DailyAllocationService;

class CrmSettingController extends Controller
{
    public function __construct(private readonly CrmSettingsService $settings) {}

    public function saveOutcome(SaveOutcomeRequest $request)
    {
        $this->settings->saveOutcome($request->validated());

        return $this->toast('Outcome saved. Existing leads update on their next edit.');
    }

    public function addOption(SaveOptionRequest $request)
    {
        $this->settings->addOption($request->validated('type'), $request->validated('value'));

        return $this->toast('Option added.');
    }

    public function removeOption(SaveOptionRequest $request)
    {
        $this->settings->removeOption($request->validated('type'), $request->validated('value'));

        return $this->toast('Option removed.');
    }

    public function target(DailyTargetRequest $request, DailyAllocationService $daily)
    {
        $daily->setTarget((int) $request->validated('slots'));

        return $this->toast('Daily lead target updated.');
    }
}
