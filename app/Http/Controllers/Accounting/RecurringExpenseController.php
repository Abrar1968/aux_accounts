<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreRecurringExpenseRequest;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Services\Accounting\RecurringExpenseService;
use App\Support\Period;
use Inertia\Inertia;
use Inertia\Response;

class RecurringExpenseController extends Controller
{
    public function __construct(private readonly RecurringExpenseService $recurring) {}

    public function index(): Response
    {
        return Inertia::render('Accounting/Recurring', [
            'templates' => RecurringExpense::query()->withCount('bills')->orderBy('name')->get(),
            'categories' => ExpenseCategory::query()->orderBy('sort')->pluck('name'),
            'month' => Period::current()->key(),
        ]);
    }

    public function store(StoreRecurringExpenseRequest $request)
    {
        $t = $this->recurring->create($request->validated());

        return $this->toast("Template “{$t->name}” saved. Bills are created by the daily automation.");
    }

    public function update(StoreRecurringExpenseRequest $request, RecurringExpense $recurring)
    {
        $this->recurring->update($recurring, $request->validated());

        return $this->toast("Template “{$recurring->name}” updated.");
    }

    public function generate(string $month)
    {
        $n = $this->recurring->generate(Period::of($month));

        return $this->toast("{$n} bill(s) created for ".Period::of($month)->label().'.');
    }
}
