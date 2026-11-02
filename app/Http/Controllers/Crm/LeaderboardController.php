<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\SetTargetRequest;
use App\Services\Crm\LeaderboardService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function __construct(private readonly LeaderboardService $board) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Crm/Leaderboard', $this->board->board(Period::of($request->query('month', Period::current()->key()))));
    }

    public function target(SetTargetRequest $request)
    {
        $d = $request->validated();
        $this->board->setTarget($d['owner'], Period::of($d['month']), $d);

        return $this->toast("Targets saved for {$d['owner']}.");
    }
}
