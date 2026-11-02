<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\CashForecastService;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function __invoke(CashForecastService $forecast): Response
    {
        return Inertia::render('Accounting/Forecast', ['forecast' => $forecast->forecast()]);
    }
}
