<?php

namespace App\Services\System;

use App\Models\User;
use App\Repositories\Contracts\CustomerPaymentRepositoryInterface;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\OperatingExpenseRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;

/** Global search (Ctrl K) across modules the user may see. */
class SearchService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly CustomerPaymentRepositoryInterface $payments,
        private readonly OperatingExpenseRepositoryInterface $expenses,
        private readonly EmployeeRepositoryInterface $employees,
        private readonly LeadRepositoryInterface $leads,
    ) {}

    public function search(User $user, string $q, int $limit = 6): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $out = [];
        $add = function (string $perm, string $group, $repo, callable $map) use ($user, $q, $limit, &$out) {
            if ($user->hasPermission($perm)) {
                foreach ($repo->paginate(['q' => $q], $limit)->items() as $m) {
                    $out[] = ['group' => $group] + $map($m);
                }
            }
        };
        $add('accounting.projects.view', 'Projects', $this->projects, fn ($p) => ['title' => $p->name, 'subtitle' => $p->code, 'url' => '/accounting/projects/'.$p->id]);
        $add('accounting.payments.view', 'Payments', $this->payments, fn ($p) => ['title' => $p->code, 'subtitle' => $p->canonical_name.' · ৳'.number_format((float) $p->cash_received), 'url' => '/accounting/payments?q='.$p->code]);
        $add('accounting.expenses.view', 'Expenses', $this->expenses, fn ($e) => ['title' => $e->description ?: $e->code, 'subtitle' => $e->code.' · '.$e->category, 'url' => '/accounting/expenses/'.$e->id]);
        $add('accounting.payroll.view', 'Employees', $this->employees, fn ($e) => ['title' => $e->name, 'subtitle' => $e->designation ?? 'Employee', 'url' => '/accounting/payroll?employee='.$e->id]);
        $add('crm.leads.view', 'Leads', $this->leads, fn ($l) => ['title' => $l->phone, 'subtitle' => $l->code.' · '.$l->outcome, 'url' => '/crm/leads/'.$l->id]);

        return $out;
    }
}
