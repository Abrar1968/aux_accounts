<?php

namespace Tests\Feature\Http;

use App\Models\Lead;
use App\Models\OperatingExpense;
use App\Models\Payroll;
use App\Models\Project;
use App\Services\Crm\CrmReportService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Every Inertia page renders for an owner with the real seeded data. */
class PagesRenderTest extends TestCase
{
    use SeedsWorkbook;

    public function test_all_pages_render(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        $owner = $this->owner();
        $report = app(CrmReportService::class)->snapshot('Monthly', '2026-10-31');
        $pages = [
            '/' => 'Dashboard',
            '/accounting/statements/2026-10' => 'Accounting/Statements/Show',
            '/accounting/statements/grid' => 'Accounting/Statements/Grid',
            '/accounting/controls' => 'Accounting/Controls',
            '/accounting/projects' => 'Accounting/Projects/Index',
            '/accounting/projects/'.Project::query()->first()->id => 'Accounting/Projects/Show',
            '/accounting/recognition' => 'Accounting/Recognition/Index',
            '/accounting/payments' => 'Accounting/Payments/Index',
            '/accounting/receivables?month=2026-10' => 'Accounting/Receivables',
            '/accounting/marketing' => 'Accounting/Marketing/Index',
            '/accounting/partnerships' => 'Accounting/Partnerships/Index',
            '/accounting/expenses' => 'Accounting/Expenses/Index',
            '/accounting/expenses/'.OperatingExpense::query()->first()->id => 'Accounting/Expenses/Show',
            '/accounting/employees' => 'Accounting/Employees/Index',
            '/accounting/payroll?month=2026-10' => 'Accounting/Payroll/Index',
            '/accounting/payroll/'.Payroll::query()->first()->id.'/payslip' => 'Accounting/Payroll/Payslip',
            '/accounting/advances' => 'Accounting/Advances/Index',
            '/accounting/referrals' => 'Accounting/Referrals/Index',
            '/accounting/assets' => 'Accounting/Assets/Index',
            '/accounting/liabilities' => 'Accounting/Liabilities/Index',
            '/accounting/capital' => 'Accounting/Capital/Index',
            '/accounting/periods' => 'Accounting/Periods',
            '/crm' => 'Crm/Today',
            '/crm/leads' => 'Crm/Leads/Index',
            '/crm/leads/'.Lead::query()->first()->id => 'Crm/Leads/Show',
            '/crm/pipeline' => 'Crm/Pipeline',
            '/crm/follow-ups' => 'Crm/FollowUps',
            '/crm/meetings' => 'Crm/Meetings',
            '/crm/reports' => 'Crm/Reports/Index',
            '/crm/reports/'.$report->id => 'Crm/Reports/Show',
            '/crm/analytics' => 'Crm/Analytics',
            '/settings' => 'System/Settings',
            '/system/automation' => 'System/Automation',
            '/system/audit' => 'System/Audit',
            '/system/users' => 'System/Users',
            '/system/roles' => 'System/Roles',
            '/notifications' => 'Notifications/Index',
            '/profile' => 'Profile/Edit',
            '/profile/notifications' => 'Profile/Notifications',
        ];
        foreach ($pages as $url => $component) {
            $this->actingAs($owner)->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($component));
        }
        $this->actingAs($owner)->getJson('/ajax/search?q=wtb')->assertOk()->assertJsonStructure(['results']);
        $this->actingAs($owner)->getJson('/ajax/receivables?q=LEG')->assertOk();
        $this->actingAs($owner)->getJson('/ajax/leads/phone-check?phone=01768093784')->assertOk()->assertJsonPath('normalized', '8801768093784');
    }

    public function test_dashboard_kpis_use_statement_engine(): void
    {
        $this->actingAs($this->owner())->get('/?month=2026-10')->assertInertia(fn (Assert $p) => $p
            ->where('overview.kpis.ending_cash', -62160)
            ->where('overview.kpis.balance_check', 0)
            ->has('overview.trend', 7));
    }
}
