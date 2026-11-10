<?php

namespace App\Services\Banking;

use App\Exceptions\Domain\DomainException;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use App\Repositories\Contracts\BankReconciliationRepositoryInterface;
use App\Repositories\Contracts\CapitalTransactionRepositoryInterface;
use App\Repositories\Contracts\CustomerPaymentRepositoryInterface;
use App\Repositories\Contracts\EmployeeAdvanceRepositoryInterface;
use App\Repositories\Contracts\ExpensePaymentRepositoryInterface;
use App\Repositories\Contracts\FixedAssetRepositoryInterface;
use App\Repositories\Contracts\LiabilityRepositoryInterface;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Repositories\Contracts\SalaryPaymentRepositoryInterface;
use App\Services\Shared\SettingsService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Bank / wallet / cash accounts and reconciliation (FR-ACC-86, new).
 *
 * Every cash movement used by the statement's cash reconciliation is allocated to an account by
 * matching the ledger's `account` (then `method`, then liability source text) against account
 * aliases. Anything unmatched stays in "Unassigned" — so Σ accounts + unassigned always equals the
 * statement's ending cash. Account opening balances are an allocation of the opening cash setting.
 */
class BankingService
{
    public function __construct(
        private readonly BankAccountRepositoryInterface $accounts,
        private readonly BankReconciliationRepositoryInterface $reconciliations,
        private readonly CustomerPaymentRepositoryInterface $payments,
        private readonly ExpensePaymentRepositoryInterface $expensePayments,
        private readonly SalaryPaymentRepositoryInterface $salaryPayments,
        private readonly ReferralRepositoryInterface $referrals,
        private readonly EmployeeAdvanceRepositoryInterface $advances,
        private readonly CapitalTransactionRepositoryInterface $capital,
        private readonly LiabilityRepositoryInterface $liabilities,
        private readonly FixedAssetRepositoryInterface $assets,
        private readonly SettingsService $settings,
    ) {}

    public function create(array $data): BankAccount
    {
        return $this->accounts->create($this->normaliseAliases($data) + ['sort' => (int) $this->accounts->query()->max('sort') + 1]);
    }

    public function update(BankAccount $a, array $data): BankAccount
    {
        return $this->accounts->update($a, $this->normaliseAliases($data));
    }

    public function delete(BankAccount $a): void
    {
        if ($a->reconciliations()->exists()) {
            throw new DomainException("{$a->name} has reconciliations. Mark it inactive instead.");
        }
        $this->accounts->delete($a);
    }

    public function deleteReconciliation(BankReconciliation $r): void
    {
        $r->delete();
    }

    private function normaliseAliases(array $data): array
    {
        if (array_key_exists('aliases', $data)) {
            $list = is_array($data['aliases']) ? $data['aliases'] : explode(',', (string) $data['aliases']);
            $data['aliases'] = array_values(array_unique(array_filter(array_map(fn ($a) => mb_strtolower(trim($a)), $list))));
        }

        return $data;
    }

    /** @return Collection<int, array{date:string, source:string, reference:string, description:string, amount:float, account_id:?int}> */
    public function movements(?string $from = null, ?string $to = null): Collection
    {
        $matcher = $this->matcher();
        $rows = collect();
        $add = function (string $date, string $source, string $ref, ?string $desc, float $amount, array $hints) use (&$rows, $matcher) {
            $d = substr($date, 0, 10);
            if (round($amount, 2) == 0.0) {
                return;
            }
            $rows->push(['date' => $d, 'source' => $source, 'reference' => $ref, 'description' => (string) $desc,
                'amount' => Money::round($amount), 'account_id' => $matcher($hints)]);
        };

        foreach ($this->payments->query()->get() as $p) {
            $add((string) $p->paid_at, 'Customer receipt', $p->code, $p->canonical_name, (float) $p->cash_received, [$p->account, $p->method]);
        }
        foreach ($this->expensePayments->query()->with('expense:id,description')->get() as $p) {
            $add((string) $p->paid_on, 'Expense payment', $p->code, $p->expense?->description, -$p->cashOut(), [$p->account, $p->method]);
        }
        foreach ($this->salaryPayments->query()->with('payroll.employee:id,name')->get() as $p) {
            $add((string) $p->paid_on, 'Salary payment', $p->code, $p->payroll?->employee?->name, -(float) $p->amount, [$p->account, $p->method]);
        }
        foreach ($this->referrals->query()->whereNotNull('paid_on')->where('paid_amount', '>', 0)->get() as $r) {
            $add((string) $r->paid_on, 'Referral paid', $r->code, $r->referrer, -(float) $r->paid_amount, []);
        }
        foreach ($this->advances->query()->where('type', 'Disbursement')->with('employee:id,name')->get() as $a) {
            $add((string) $a->txn_date, 'Advance disbursed', $a->code, $a->employee?->name, -(float) $a->amount, []);
        }
        foreach ($this->capital->query()->where('cash_impact', '>', 0)->with('investor:id,name')->get() as $c) {
            $add((string) $c->txn_date, 'Capital in', $c->type, $c->investor?->name, (float) $c->cash_impact, [$c->source_status]);
        }
        foreach ($this->liabilities->query()->where('cash_impact', '!=', 0)->get() as $l) {
            $add((string) $l->txn_date, 'Liability', $l->liability_code, $l->description, (float) $l->cash_impact, [$l->source_status]);
        }
        foreach ($this->assets->query()->whereNotNull('cash_payment_date')->where('cash_paid', '>', 0)->get() as $a) {
            $add((string) $a->cash_payment_date, 'Asset purchase', $a->code, $a->name, -(float) $a->cash_paid, []);
        }

        return $rows->filter(fn ($r) => ($from === null || $r['date'] >= $from) && ($to === null || $r['date'] <= $to))
            ->sortBy([['date', 'asc'], ['reference', 'asc']])->values();
    }

    /** Returns a closure mapping hint strings to an account id (null = unassigned). */
    private function matcher(): \Closure
    {
        $accounts = $this->accounts->query()->orderBy('sort')->get(['id', 'name', 'aliases']);

        return function (array $hints) use ($accounts): ?int {
            foreach ($hints as $hint) {
                $h = mb_strtolower(trim((string) $hint));
                if ($h === '') {
                    continue;
                }
                foreach ($accounts as $a) {
                    foreach (array_merge([mb_strtolower($a->name)], $a->aliases ?? []) as $alias) {
                        if ($alias !== '' && ($h === $alias || preg_match('/\b'.preg_quote($alias, '/').'\b/u', $h))) {
                            return $a->id;
                        }
                    }
                }
            }

            return null;
        };
    }

    /** Balances per account as at a date (inclusive). */
    public function balances(?string $asOf = null): array
    {
        $asOf ??= now()->toDateString();
        $moves = $this->movements(null, $asOf);
        $out = [];
        $allocatedOpening = 0.0;
        foreach ($this->accounts->query()->orderBy('sort')->get() as $a) {
            $mine = $moves->where('account_id', $a->id);
            $opening = (float) $a->opening_balance;
            $allocatedOpening += $opening;
            $last = $a->reconciliations()->orderByDesc('statement_date')->first();
            $out[] = [
                'id' => $a->id, 'name' => $a->name, 'type' => $a->type, 'account_number' => $a->account_number,
                'aliases' => $a->aliases ?? [], 'is_active' => $a->is_active, 'opening' => Money::round($opening),
                'inflow' => Money::sum($mine->where('amount', '>', 0)->pluck('amount')),
                'outflow' => Money::round(-Money::sum($mine->where('amount', '<', 0)->pluck('amount'))),
                'balance' => Money::round($opening + Money::sum($mine->pluck('amount'))),
                'movements' => $mine->count(),
                'last_reconciliation' => $last?->only(['statement_date', 'statement_balance', 'difference', 'status']),
            ];
        }
        $un = $moves->whereNull('account_id');
        $unOpening = Money::round((float) $this->settings->get('opening.cash') - $allocatedOpening);
        $unassigned = [
            'opening' => $unOpening, 'inflow' => Money::sum($un->where('amount', '>', 0)->pluck('amount')),
            'outflow' => Money::round(-Money::sum($un->where('amount', '<', 0)->pluck('amount'))),
            'balance' => Money::round($unOpening + Money::sum($un->pluck('amount'))), 'movements' => $un->count(),
        ];

        return ['as_of' => $asOf, 'accounts' => $out, 'unassigned' => $unassigned,
            'total' => Money::round(Money::sum(array_column($out, 'balance')) + $unassigned['balance'])];
    }

    public function bookBalance(BankAccount $a, string $date): float
    {
        $moves = $this->movements(null, $date)->where('account_id', $a->id);

        return Money::round((float) $a->opening_balance + Money::sum($moves->pluck('amount')));
    }

    public function reconcile(BankAccount $a, string $date, float $statementBalance, ?string $notes = null): BankReconciliation
    {
        if ($date > now()->toDateString()) {
            throw new DomainException('Statement date cannot be in the future.');
        }
        $book = $this->bookBalance($a, $date);
        $diff = Money::round($statementBalance - $book);

        return $this->reconciliations->query()->updateOrCreate(
            ['bank_account_id' => $a->id, 'statement_date' => $date],
            ['statement_balance' => Money::round($statementBalance), 'book_balance' => $book, 'difference' => $diff,
                'status' => abs($diff) < 0.01 ? 'matched' : 'difference', 'notes' => $notes, 'reconciled_by' => Auth::id()],
        );
    }

    public function history(BankAccount $a): Collection
    {
        return $a->reconciliations()->orderByDesc('statement_date')->get();
    }

    /** Account statement with running balance; $account null = unassigned movements. */
    public function ledger(?BankAccount $account, string $from, string $to): array
    {
        $opening = ($account ? (float) $account->opening_balance : 0.0)
            + Money::sum($this->movements(null, date('Y-m-d', strtotime($from.' -1 day')))->where('account_id', $account?->id)->pluck('amount'));
        $balance = Money::round($opening);
        $rows = $this->movements($from, $to)->where('account_id', $account?->id)->values()->map(function ($m) use (&$balance) {
            $balance = Money::round($balance + $m['amount']);

            return $m + ['balance' => $balance];
        });

        return ['opening' => Money::round($opening), 'rows' => $rows->all(), 'closing' => $balance];
    }
}
