<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\RecurringExpense;
use App\Repositories\Contracts\OperatingExpenseRepositoryInterface;
use App\Repositories\Contracts\RecurringExpenseRepositoryInterface;
use App\Services\Shared\PeriodService;
use App\Support\Period;
use Carbon\CarbonImmutable;

/**
 * Recurring expense templates (FR-ACC-53, new): rent, wifi, hosting… Each active template creates
 * one unpaid bill per month (idempotent — unique template + accrual month). Bills are paid normally.
 */
class RecurringExpenseService
{
    public function __construct(
        private readonly RecurringExpenseRepositoryInterface $templates,
        private readonly OperatingExpenseRepositoryInterface $bills,
        private readonly ExpenseService $expenses,
        private readonly PeriodService $periods,
    ) {}

    public function create(array $data): RecurringExpense
    {
        return $this->templates->create($this->normalise($data));
    }

    public function update(RecurringExpense $t, array $data): RecurringExpense
    {
        return $this->templates->update($t, $this->normalise($data));
    }

    /** Bills already generated stay (their template link is cleared by the FK). */
    public function delete(RecurringExpense $t): void
    {
        $this->templates->delete($t);
    }

    private function normalise(array $data): array
    {
        foreach (['start_month', 'end_month'] as $k) {
            if (! empty($data[$k])) {
                $data[$k] = Period::of($data[$k])->date();
            }
        }
        if (! empty($data['end_month']) && ! empty($data['start_month']) && $data['end_month'] < $data['start_month']) {
            throw new DomainException('End month must be after the start month.');
        }

        return $data;
    }

    public function activeFor(Period $p)
    {
        return $this->templates->query()->where('is_active', true)->where('start_month', '<=', $p->date())
            ->where(fn ($q) => $q->whereNull('end_month')->orWhere('end_month', '>=', $p->date()))->get();
    }

    /** Creates this month's bills for every active template (skips existing ones and closed periods). */
    public function generate(Period $p): int
    {
        if ($this->periods->isLocked($p->date())) {
            return 0;
        }
        $made = 0;
        foreach ($this->activeFor($p) as $t) {
            if ($this->bills->query()->where('recurring_expense_id', $t->id)->where('accrual_month', $p->date())->exists()) {
                continue;
            }
            $day = min($t->day_of_month, CarbonImmutable::parse($p->date())->daysInMonth);
            $this->expenses->create([
                'expense_date' => $p->start->setDay($day)->toDateString(), 'accrual_period' => $p->date(),
                'category' => $t->category, 'subcategory' => $t->subcategory, 'description' => $t->name,
                'amount' => $t->amount, 'recurring_expense_id' => $t->id, 'notes' => 'Generated from recurring template',
            ]);
            $made++;
        }

        return $made;
    }
}
