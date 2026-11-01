<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ControlsService;
use App\Support\Period;
use Inertia\Inertia;
use Inertia\Response;

class ControlController extends Controller
{
    public function __invoke(ControlsService $controls): Response
    {
        return Inertia::render('Accounting/Controls', ['controls' => $controls->run(Period::current())]);
    }
}
