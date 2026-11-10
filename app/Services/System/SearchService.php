<?php

namespace App\Services\System;

use App\Models\User;
use App\Repositories\Contracts\CustomerPaymentRepositoryInterface;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\MarketingServiceRepositoryInterface;
use App\Repositories\Contracts\OperatingExpenseRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Support\Search;

/** Global search (Ctrl K) across modules the user may see. */
class SearchService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly CustomerPaymentRepositoryInterface $payments,
        private readonly OperatingExpenseRepositoryInterface $expenses,
        private readonly EmployeeRepositoryInterface $employees,
        private readonly LeadRepositoryInterface $leads,
        private readonly MarketingServiceRepositoryInterface $marketing,
    ) {}

    public function search(User $user, string $q, int $limit = 5): array
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
        $add('accounting.projects.view', 'Projects', $this->projects, fn ($p) => ['title' => $p->name, 'subtitle' => implode(' · ', array_filter([$p->code, $p->customer, $p->client_email, $p->client_phone])), 'url' => '/accounting/projects/'.$p->id, 'icon' => 'briefcase']);
        $add('accounting.projects.view', 'Marketing services', $this->marketing, fn ($m) => ['title' => $m->client, 'subtitle' => $m->code.' · '.optional($m->service_month)->format('M Y'), 'url' => '/accounting/marketing?q='.urlencode($m->code), 'icon' => 'megaphone']);
        $add('accounting.payments.view', 'Payments', $this->payments, fn ($p) => ['title' => $p->code, 'subtitle' => $p->canonical_name.' · ৳'.number_format((float) $p->cash_received), 'url' => '/accounting/payments?q='.$p->code, 'icon' => 'inbox']);
        $add('accounting.expenses.view', 'Expenses', $this->expenses, fn ($e) => ['title' => $e->description ?: $e->code, 'subtitle' => $e->code.' · '.$e->category, 'url' => '/accounting/expenses/'.$e->id, 'icon' => 'receipt']);
        $add('accounting.payroll.view', 'Employees', $this->employees, fn ($e) => ['title' => $e->name, 'subtitle' => $e->designation ?? 'Employee', 'url' => '/accounting/payroll?employee='.$e->id, 'icon' => 'user']);
        $add('crm.leads.view', 'Leads', $this->leads, fn ($l) => ['title' => $l->phone, 'subtitle' => implode(' · ', array_filter([$l->code, $l->business_type, $l->outcome, $l->owner])), 'url' => '/crm/leads/'.$l->id, 'icon' => 'phone']);
        if ($user->hasPermission('users.manage')) {
            foreach (User::query()->where(fn ($w) => Search::apply($w, $q, ['name', 'email']))->limit($limit)->get() as $u) {
                $out[] = ['group' => 'Users', 'title' => $u->name, 'subtitle' => $u->email, 'url' => '/system/users?q='.urlencode($u->email), 'icon' => 'users'];
            }
        }

        return $out;
    }
}
