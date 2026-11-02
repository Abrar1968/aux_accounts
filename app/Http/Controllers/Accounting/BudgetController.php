<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\SaveBudgetRequest;
use App\Services\Accounting\BudgetService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function index(Request $request): Response
    {
        $p = Period::of($request->query('month', Period::current()->key()));

        return Inertia::render('Accounting/Budgets', ['month' => $p->key(), 'rows' => $this->budgets->compare($p)]);
    }

    public function update(SaveBudgetRequest $request, string $month)
    {
        $n = $this->budgets->save(Period::of($month), $request->validated('budgets'));

        return $this->toast("{$n} budget line(s) saved for ".Period::of($month)->label().'.');
    }

    public function copy(string $month)
    {
        $n = $this->budgets->copyPrevious(Period::of($month));

        return $this->toast("{$n} budget line(s) copied from the previous month.");
    }
}
