<?php

namespace App\Services\Shared;

use App\Repositories\Contracts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Uniform list/filter/paginate for index pages (q, from, to, sort, per_page) through the
 * repository layer, with per-list eager loads and extra filters.
 */
class ListingService
{
    private const REPOS = [
        'projects' => [Contracts\ProjectRepositoryInterface::class, ['referral']],
        'recognitions' => [Contracts\RevenueRecognitionRepositoryInterface::class, ['project:id,code,name']],
        'payments' => [Contracts\CustomerPaymentRepositoryInterface::class, []],
        'marketing' => [Contracts\MarketingServiceRepositoryInterface::class, []],
        'expenses' => [Contracts\OperatingExpenseRepositoryInterface::class, ['payments']],
        'expense_payments' => [Contracts\ExpensePaymentRepositoryInterface::class, ['expense:id,code,description']],
        'employees' => [Contracts\EmployeeRepositoryInterface::class, []],
        'payrolls' => [Contracts\PayrollRepositoryInterface::class, ['employee']],
        'salary_payments' => [Contracts\SalaryPaymentRepositoryInterface::class, ['payroll.employee']],
        'advances' => [Contracts\EmployeeAdvanceRepositoryInterface::class, ['employee:id,name']],
        'referrals' => [Contracts\ReferralRepositoryInterface::class, ['project:id,code,name,source_month']],
        'assets' => [Contracts\FixedAssetRepositoryInterface::class, []],
        'liabilities' => [Contracts\LiabilityRepositoryInterface::class, []],
        'capital' => [Contracts\CapitalTransactionRepositoryInterface::class, ['investor:id,name']],
        'partnership_revenues' => [Contracts\PartnershipRevenueRepositoryInterface::class, ['assignment.collaborator', 'assignment.package']],
        'leads' => [Contracts\LeadRepositoryInterface::class, ['calls']],
        'crm_reports' => [Contracts\CrmReportRepositoryInterface::class, []],
    ];

    public function page(string $list, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        [$contract, $with] = self::REPOS[$list] ?? throw new \InvalidArgumentException("Unknown list $list");
        $page = app($contract)->paginate($filters, (int) ($filters['per_page'] ?? $perPage));
        $page->getCollection()->loadMissing($with);

        return $page;
    }
}
