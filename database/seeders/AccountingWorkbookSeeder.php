<?php

namespace Database\Seeders;

use App\Models\CapitalTransaction;
use App\Models\Collaborator;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\ExpenseCategory;
use App\Models\ExpensePayment;
use App\Models\FixedAsset;
use App\Models\Investor;
use App\Models\InvestorOwnership;
use App\Models\Liability;
use App\Models\MarketingService;
use App\Models\OpeningReceivable;
use App\Models\OperatingExpense;
use App\Models\PartnershipAssignment;
use App\Models\PartnershipPackage;
use App\Models\Payroll;
use App\Models\Period as PeriodModel;
use App\Models\Project;
use App\Models\Referral;
use App\Models\RevenueRecognition;
use App\Models\SalaryPayment;
use App\Models\SourceAlias;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\PayrollService;
use App\Services\Accounting\RevenueRecognitionService;
use App\Services\Shared\SequenceService;
use App\Support\Period;
use Illuminate\Database\Seeder;

/**
 * Loads the Auxtech Accrual Accounting Master 2026 workbook (docs/12-data-migration.md).
 */
class AccountingWorkbookSeeder extends Seeder
{
    use LoadsWorkbookJson;

    /** @var array<string,int> */
    private array $projects = [];

    /** @var array<string,int> */
    private array $employees = [];

    public function run(): void
    {
        $this->aliases();
        $this->periods();
        $this->capital();
        $this->openingReceivables();
        $this->projectsAndRecognition();
        $this->marketing();
        $this->partnerships();
        $this->payments();
        $this->expenses();
        $this->payroll();
        $this->advances();
        $this->referrals();
        $this->assetsAndLiabilities();
        $this->sequences();
    }

    private function aliases(): void
    {
        foreach ($this->json('accounting/source_mapping') as $r) {
            SourceAlias::query()->create([
                'alias' => $r['Source Alias'], 'canonical' => $r['Canonical Name / ID'] ?? '', 'type' => $r['Type'] ?? null,
                'decision' => $r['Decision'] ?? null, 'confirmed' => ($r['Confirmed By User'] ?? '') === 'Yes', 'notes' => $r['Notes'] ?? null,
            ]);
        }
    }

    private function periods(): void
    {
        $historicalThrough = Period::of('2026-09-01');
        foreach (Period::range(Period::of('2026-04-01'), Period::of(max('2026-11-01', Period::current()->date()))) as $p) {
            PeriodModel::query()->updateOrCreate(['month' => $p->date()], [
                'status' => $p->lte($historicalThrough) ? 'historical' : 'open',
                'notes' => $p->lte($historicalThrough) ? 'Imported/reconstructed' : null,
            ]);
        }
    }

    private function capital(): void
    {
        foreach ($this->json('accounting/investors') as $r) {
            if (! array_key_exists('Ownership Apr–Aug', $r)) {
                continue; // explanatory note row
            }
            $inv = Investor::query()->create([
                'name' => $r['Investor'], 'role_label' => $r['Role'] ?? null,
                'is_equity' => ! str_contains((string) ($r['Role'] ?? ''), 'not equity'),
                'capital_commitment' => $this->num($r['Capital Commitment'] ?? 0),
            ]);
            InvestorOwnership::query()->create(['investor_id' => $inv->id, 'effective_from' => '2026-04-01', 'percent' => $this->num($r['Ownership Apr–Aug'])]);
            InvestorOwnership::query()->create(['investor_id' => $inv->id, 'effective_from' => '2026-09-01', 'percent' => $this->num($r['Ownership From Sep-1'])]);
        }
        $investors = Investor::query()->pluck('id', 'name');
        foreach ($this->json('accounting/capital_ledger') as $r) {
            CapitalTransaction::query()->create([
                'txn_date' => $r['Date'], 'period' => $r['Period'] ?? Period::of($r['Date'])->date(),
                'investor_id' => $investors[$r['Investor']], 'type' => $r['Transaction Type'],
                'cash_impact' => $this->num($r['Cash Impact'] ?? 0), 'equity_impact' => $this->num($r['Equity Impact'] ?? 0),
                'counterparty' => $this->str($r['Counterparty'] ?? null), 'ownership_impact' => $this->str($r['Ownership Impact'] ?? null),
                'source_status' => $this->str($r['Source / Status'] ?? null), 'notes' => $this->str($r['Notes'] ?? null),
            ]);
        }
    }

    private function openingReceivables(): void
    {
        foreach ($this->json('accounting/opening_ar') as $r) {
            OpeningReceivable::query()->create([
                'code' => $r['Revenue ID'], 'customer' => $r['Customer / Portfolio'], 'opening_amount' => $this->num($r['Opening A/R']),
                'details' => $this->str($r['Pre-Apr Details'] ?? null), 'status' => $this->str($r['Status'] ?? null), 'notes' => $this->str($r['Notes'] ?? null),
            ]);
        }
    }

    private function projectsAndRecognition(): void
    {
        $load = function (array $rows, string $defaultKind) {
            foreach ($rows as $r) {
                $kind = ($r['Type'] ?? $r['Type (Auto)'] ?? '') === 'Lead' ? 'lead' : $defaultKind;
                $p = Project::query()->create([
                    'code' => $r['Project ID'], 'name' => $r['Project Name'], 'customer' => $r['Customer'] ?? $r['Project Name'],
                    'kind' => $kind, 'source_month' => $r['Source Month'] ?? null, 'start_date' => $r['Start Date'] ?? null,
                    'est_delivery_date' => $r['Est. Delivery Date'] ?? null, 'actual_delivery_date' => $r['Actual Delivery Date'] ?? null,
                    'revenue_method' => $r['Revenue Method'], 'base_value' => $this->num($r['Base Contract Value'] ?? 0),
                    'change_orders' => $this->num($r['Change Orders'] ?? 0), 'referrer' => $this->str($r['Referrer'] ?? null),
                    'previous_status' => $this->str($r['Previous Status (Reference)'] ?? null), 'notes' => $this->str($r['Notes'] ?? null),
                ]);
                $this->projects[$p->code] = $p->id;
            }
        };
        $load($this->json('accounting/projects'), 'project');
        $load($this->json('accounting/international_projects'), 'international');

        foreach ($this->json('accounting/revenue_recognition') as $r) {
            RevenueRecognition::query()->create([
                'code' => $r['Recognition ID'], 'period' => $r['Period'], 'project_id' => $this->projects[$r['Project ID']],
                'method_label' => $r['Method'] ?? null, 'contract_value' => $this->num($r['Contract / Change Value'] ?? 0),
                'auto_revenue' => $this->num($r['Auto Revenue'] ?? 0),
                'override_amount' => isset($r['Override Revenue']) ? $this->num($r['Override Revenue']) : null,
                'ending_progress' => isset($r['Ending Cum. Progress']) ? $this->num($r['Ending Cum. Progress']) : null,
                'recognition_month' => Period::of($r['Period'])->date(), 'is_historical' => true, 'notes' => $this->str($r['Notes'] ?? null),
            ]);
        }
        $svc = app(RevenueRecognitionService::class);
        foreach (Project::query()->get() as $p) {
            $svc->recalculateProject($p);
        }
    }

    private function marketing(): void
    {
        foreach ($this->json('accounting/marketing_services') as $r) {
            MarketingService::query()->create([
                'code' => $r['Service ID'], 'service_month' => $r['Service Month'], 'client' => $r['Client'],
                'revenue' => $this->num($r['Revenue Earned']), 'source' => $this->str($r['Source'] ?? null), 'notes' => $this->str($r['Notes'] ?? null),
            ]);
        }
    }

    private function partnerships(): void
    {
        foreach ($this->json('accounting/collaborators') as $r) {
            Collaborator::query()->create([
                'code' => $r['Collaborator ID'], 'name' => $r['Collaborator / Company Name'], 'status' => $r['Status'] ?? 'Draft',
                'agreement_start' => $r['Agreement Start'] ?? null, 'agreement_end' => $r['Agreement End'] ?? null, 'notes' => $this->str($r['Notes'] ?? null),
            ]);
        }
        $collabs = Collaborator::query()->pluck('id', 'code');
        foreach ($this->json('accounting/partnership_packages') as $r) {
            PartnershipPackage::query()->create([
                'code' => $r['Package ID'], 'name' => $r['Package Name'], 'status' => 'Active', // sheet C (all Active); C/L share a header
                'description' => $this->str($r['Description'] ?? null), 'accounting_note' => $this->str($r['Accounting Note'] ?? null),
            ]);
        }
        $packages = PartnershipPackage::query()->pluck('id', 'code');
        foreach ($this->json('accounting/partnership_packages') as $r) {
            if (empty($r['Assignment ID']) || empty($r['Collaborator ID'])) {
                continue;
            }
            PartnershipAssignment::query()->create([
                'code' => $r['Assignment ID'], 'collaborator_id' => $collabs[$r['Collaborator ID']], 'package_id' => $packages[$r['Package ID']],
                'effective_from' => $r['Effective From'] ?? null, 'effective_through' => $r['Effective Through'] ?? null,
                'status' => $r['Status'] ?? 'Draft', // column L (assignment status)
                'earning_method' => $r['Earning Calculation'] ?? 'Direct Amount',
                'share_percent' => isset($r['Auxtech Share %']) ? $this->num($r['Auxtech Share %']) : null,
                'notes' => $this->str($r['Notes'] ?? null),
            ]);
        }
    }

    private function payments(): void
    {
        $lookup = [
            'project' => $this->projects,
            'marketing' => MarketingService::query()->pluck('id', 'code')->all(),
            'opening' => OpeningReceivable::query()->pluck('id', 'code')->all(),
        ];
        foreach ($this->json('accounting/payments') as $r) {
            $code = $r['Revenue ID'];
            $type = match (true) {
                str_starts_with($code, 'LEG-') => 'opening',
                str_starts_with($code, 'MKT-') => 'marketing',
                str_starts_with($code, 'PART-') => 'partnership',
                default => 'project',
            };
            CustomerPayment::query()->create([
                'code' => $r['Payment ID'], 'paid_at' => $r['Date'], 'period' => Period::of($r['Date'])->date(),
                'receivable_type' => $type, 'receivable_id' => $lookup[$type][$code], 'revenue_code' => $code,
                'source_name' => $this->str($r['Source Name'] ?? null), 'canonical_name' => $this->str($r['Canonical Name'] ?? null),
                'cash_received' => $this->num($r['Cash Received']), 'processing_fee' => $this->num($r['Processing Fee'] ?? 0),
                'method' => $this->str($r['Method'] ?? null), 'account' => $this->str($r['Account/Notes'] ?? null),
                'source_workbook' => $this->str($r['Source Workbook'] ?? null),
                'created_at' => $r['Created At'] ?? now(), 'updated_at' => $r['Updated At'] ?? now(),
            ]);
        }
    }

    private function expenses(): void
    {
        $sort = 0;
        foreach (FinancialStatementService::NAMED_CATEGORIES as $name) {
            ExpenseCategory::query()->create(['name' => $name, 'statement_line' => true, 'sort' => ++$sort]);
        }
        $ids = [];
        foreach ($this->json('accounting/operating_expenses') as $r) {
            $category = $r['Category'] ?? 'Other Operating';
            ExpenseCategory::query()->firstOrCreate(['name' => $category], ['sort' => ++$sort]);
            $accrual = $r['Accrual Period'] ?? $r['Expense Date'];
            $e = OperatingExpense::query()->create([
                'code' => $r['Expense ID'], 'accrual_period' => $r['Accrual Period'] ?? null, 'expense_date' => $r['Expense Date'] ?? null,
                'accrual_month' => Period::of($accrual)->date(), 'category' => $category, 'subcategory' => $this->str($r['Subcategory'] ?? null),
                'description' => $this->str($r['Description'] ?? null), 'amount' => $this->num($r['Amount Incurred']),
                'source_kind' => $r['Source Workbook'] ?? 'Expense', 'source_sheet' => isset($r['Source Sheet']) ? (string) $r['Source Sheet'] : null,
                'legacy' => array_filter([
                    'initial_amount' => $r['Legacy Initial Amount'] ?? null,
                    'initial_date' => $r['Legacy Initial Date'] ?? null,
                    'initial_cash' => $r['Legacy Initial Cash'] ?? null,
                ], fn ($v) => $v !== null) ?: null,
                'created_at' => $r['Created At'] ?? now(), 'updated_at' => $r['Updated At'] ?? now(),
            ]);
            $ids[$e->code] = $e->id;
        }
        foreach ($this->json('accounting/expense_payments') as $r) {
            ExpensePayment::query()->create([
                'code' => $r['Payment ID (Auto)'] ?? 'EPAY-'.$r['Request ID'], 'paid_on' => $r['Payment Date'],
                'operating_expense_id' => $ids[$r['Expense ID']], 'amount' => $this->num($r['Amount Paid']),
                'method' => $this->str($r['Method'] ?? null), 'account' => $this->str($r['Account / Reference'] ?? null),
                'notes' => $this->str($r['Notes'] ?? null), 'origin' => $r['Entry Origin'] ?? 'Recorded from expense',
                'request_id' => $this->str($r['Request ID'] ?? null), 'posted_at' => $r['Posted At'] ?? null, 'posted_by' => $this->str($r['Posted By'] ?? null),
            ]);
        }
    }

    private function employee(string $name): int
    {
        $name = trim($name);

        return $this->employees[$name] ??= Employee::query()->firstOrCreate(
            ['slug' => mb_strtolower($name)], ['name' => $name, 'is_active' => true],
        )->id;
    }

    private function payroll(): void
    {
        $ids = [];
        foreach ($this->json('accounting/salary') as $r) {
            $p = Period::of($r['Period']);
            $pr = Payroll::query()->create([
                'code' => PayrollService::codeFor($p, $r['Employee']), 'period' => $p->date(), 'employee_id' => $this->employee($r['Employee']),
                'gross_salary' => $this->num($r['Gross Salary Expense'] ?? 0),
                'source_net_paid' => isset($r['Source Net Paid']) ? $this->num($r['Source Net Paid']) : null,
                'legacy_referral_snapshot' => $this->num($r['Legacy Referral Snapshot'] ?? 0),
                'legacy_unpaid_snapshot' => $this->num($r['Legacy Unpaid Snapshot'] ?? 0),
                'notes' => $this->str($r['Notes'] ?? null),
            ]);
            $ids[$pr->code] = $pr->id;
        }
        foreach ($this->json('accounting/salary_payments') as $r) {
            SalaryPayment::query()->create([
                'code' => $r['Payment ID (Auto)'], 'paid_on' => $r['Payment Date'], 'payroll_id' => $ids[$r['Payroll ID']],
                'amount' => $this->num($r['Salary Cash Paid']), 'method' => $this->str($r['Method'] ?? null),
                'account' => $this->str($r['Account / Reference'] ?? null), 'notes' => $this->str($r['Notes'] ?? null),
                'origin' => $r['Origin'] ?? 'Recorded from salary', 'request_id' => $this->str($r['Request ID'] ?? null),
                'posted_at' => $r['Posted At'] ?? null, 'posted_by' => $this->str($r['Posted By'] ?? null),
            ]);
        }
        // Base salary = latest gross per employee (used for drafting future payroll months).
        foreach (Employee::query()->get() as $e) {
            $latest = Payroll::query()->where('employee_id', $e->id)->where('gross_salary', '>', 0)->orderByDesc('period')->first();
            $e->update(['base_salary' => $latest?->gross_salary]);
        }
    }

    private function advances(): void
    {
        $payrolls = Payroll::query()->pluck('id', 'code');
        foreach ($this->json('accounting/employee_advances') as $r) {
            EmployeeAdvance::query()->create([
                'code' => $r['Transaction ID'], 'txn_date' => $r['Period'], 'employee_id' => $this->employee($r['Employee']),
                'type' => $r['Type'], 'amount' => $this->num($r['Amount']), 'related_salary_period' => $r['Related Salary Period'] ?? null,
                'payroll_id' => isset($r['Recovery Payroll ID (Auto)']) ? ($payrolls[$r['Recovery Payroll ID (Auto)']] ?? null) : null,
                'source_workbook' => $this->str($r['Source Workbook'] ?? null), 'source_detail' => $this->str($r['Source Detail'] ?? null),
                'notes' => $this->str($r['Notes'] ?? null),
            ]);
        }
    }

    private function referrals(): void
    {
        foreach ($this->json('accounting/referrals') as $r) {
            Referral::query()->create([
                'code' => $r['Referral ID'], 'project_id' => $this->projects[$r['Project ID']], 'referrer' => $r['Referrer'],
                'memo_amount' => $this->num($r['Memo Referral Amount'] ?? 0), 'paid_on' => $r['Paid Month'] ?? null,
                'paid_amount' => $this->num($r['Paid Amount'] ?? 0), 'notes' => $this->str($r['Notes'] ?? null),
            ]);
        }
    }

    private function assetsAndLiabilities(): void
    {
        foreach ($this->json('accounting/assets') as $r) {
            FixedAsset::query()->create([
                'code' => $r['Asset ID'], 'name' => $r['Asset Name'], 'category' => $this->str($r['Category'] ?? null),
                'acquisition_date' => $r['Acquisition Date'] ?? null, 'in_service_date' => $r['In-Service Date'] ?? null,
                'cost' => $this->num($r['Cost']), 'residual_value' => $this->num($r['Residual Value'] ?? 0),
                'useful_life_months' => (int) $r['Useful Life (Months)'], 'opening_accumulated_dep' => $this->num($r['Opening Accum. Dep. 31-Mar'] ?? 0),
                'disposal_date' => $r['Disposal Date'] ?? null, 'cash_paid' => $this->num($r['Cash Paid Current Model'] ?? 0),
                'cash_payment_date' => $r['Cash Payment Date'] ?? null, 'notes' => $this->str($r['Source / Notes'] ?? null),
            ]);
        }
        foreach ($this->json('accounting/liabilities') as $r) {
            Liability::query()->create([
                'txn_date' => $r['Date'], 'period' => $r['Period'] ?? Period::of($r['Date'])->date(), 'liability_code' => $r['Liability ID'],
                'liability_type' => $r['Liability Type'] ?? 'Other', 'description' => $this->str($r['Description'] ?? null),
                'increase' => $this->num($r['Increase'] ?? 0), 'decrease' => $this->num($r['Decrease / Payment'] ?? 0),
                'cash_impact' => $this->num($r['Cash Impact'] ?? 0), 'counterparty' => $this->str($r['Counterparty'] ?? null),
                'source_status' => $this->str($r['Source / Status'] ?? null), 'notes' => $this->str($r['Notes'] ?? null),
            ]);
        }
    }

    /** Continue workbook numbering after the imported rows. */
    private function sequences(): void
    {
        $seq = app(SequenceService::class);
        $max = function ($codes, string $pattern): int {
            $n = 0;
            foreach ($codes as $c) {
                if (preg_match($pattern, (string) $c, $m)) {
                    $n = max($n, (int) $m[1]);
                }
            }

            return $n;
        };
        $seq->bump('EXP', $max(OperatingExpense::query()->pluck('code'), '/^EXP-(\d+)$/'));
        $seq->bump('PAY', max(CustomerPayment::query()->count(), $max(CustomerPayment::query()->pluck('code'), '/-(\d+)$/')));
        $seq->bump('ADV', $max(EmployeeAdvance::query()->pluck('code'), '/^ADV-(\d+)$/'));
        $seq->bump('REF', max(Referral::query()->count(), $max(Referral::query()->pluck('code'), '/^REF-(\d+)$/')));
        $seq->bump('PRJ', max(Project::query()->count(), $max(Project::query()->pluck('code'), '/^PRJ-\d{4}-(\d+)$/')));
        $seq->bump('IPRJ', $max(Project::query()->pluck('code'), '/^IPRJ-\d{4}-(\d+)$/'));
        $seq->bump('LEAD', Project::query()->where('kind', 'lead')->count());
        $seq->bump('RR', max(RevenueRecognition::query()->count() + 1, 18));
        $seq->bump('MKT', max(MarketingService::query()->count() + 1, $max(MarketingService::query()->pluck('code'), '/-(\d+)$/')));
    }
}
