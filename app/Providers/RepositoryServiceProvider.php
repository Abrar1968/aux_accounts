<?php

namespace App\Providers;

use App\Repositories\Contracts;
use App\Repositories\Eloquent;
use Illuminate\Support\ServiceProvider;

/** Binds every repository interface to its Eloquent implementation. */
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->bindings() as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }

    /** @return array<class-string, class-string> */
    public function bindings(): array
    {
        return [
            Contracts\CapitalTransactionRepositoryInterface::class => Eloquent\CapitalTransactionRepository::class,
            Contracts\CrmReportRepositoryInterface::class => Eloquent\CrmReportRepository::class,
            Contracts\CustomerPaymentRepositoryInterface::class => Eloquent\CustomerPaymentRepository::class,
            Contracts\EmployeeAdvanceRepositoryInterface::class => Eloquent\EmployeeAdvanceRepository::class,
            Contracts\EmployeeRepositoryInterface::class => Eloquent\EmployeeRepository::class,
            Contracts\ExpensePaymentRepositoryInterface::class => Eloquent\ExpensePaymentRepository::class,
            Contracts\FixedAssetRepositoryInterface::class => Eloquent\FixedAssetRepository::class,
            Contracts\InvestorRepositoryInterface::class => Eloquent\InvestorRepository::class,
            Contracts\LeadCallRepositoryInterface::class => Eloquent\LeadCallRepository::class,
            Contracts\LeadRepositoryInterface::class => Eloquent\LeadRepository::class,
            Contracts\LiabilityRepositoryInterface::class => Eloquent\LiabilityRepository::class,
            Contracts\MarketingServiceRepositoryInterface::class => Eloquent\MarketingServiceRepository::class,
            Contracts\OpeningReceivableRepositoryInterface::class => Eloquent\OpeningReceivableRepository::class,
            Contracts\OperatingExpenseRepositoryInterface::class => Eloquent\OperatingExpenseRepository::class,
            Contracts\PartnershipRevenueRepositoryInterface::class => Eloquent\PartnershipRevenueRepository::class,
            Contracts\PayrollRepositoryInterface::class => Eloquent\PayrollRepository::class,
            Contracts\PeriodRepositoryInterface::class => Eloquent\PeriodRepository::class,
            Contracts\ProjectRepositoryInterface::class => Eloquent\ProjectRepository::class,
            Contracts\ReferralRepositoryInterface::class => Eloquent\ReferralRepository::class,
            Contracts\RevenueRecognitionRepositoryInterface::class => Eloquent\RevenueRecognitionRepository::class,
            Contracts\SalaryPaymentRepositoryInterface::class => Eloquent\SalaryPaymentRepository::class,
            Contracts\SettingRepositoryInterface::class => Eloquent\SettingRepository::class,
        ];
    }
}
