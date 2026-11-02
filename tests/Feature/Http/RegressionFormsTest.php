<?php

namespace Tests\Feature\Http;

use App\Models\FixedAsset;
use App\Models\Liability;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Regressions found by the end-to-end UI audit (forms submitted with optional fields left blank). */
class RegressionFormsTest extends TestCase
{
    use SeedsWorkbook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 09:30:00');
    }

    public function test_asset_with_blank_optional_amounts(): void
    {
        $this->actingAs($this->owner())->post('/accounting/assets', ['name' => 'Laptop', 'acquisition_date' => '2026-11-01', 'in_service_date' => '2026-11-01',
            'cost' => 60000, 'residual_value' => null, 'useful_life_months' => 36, 'opening_accumulated_dep' => null, 'cash_paid' => null, 'cash_payment_date' => null])
            ->assertSessionHas('toast.type', 'warning'); // unfunded 60,000 → remind to post financing liability
        $a = FixedAsset::query()->where('name', 'Laptop')->firstOrFail();
        $this->assertSame([0.0, 0.0, 0.0], [(float) $a->residual_value, (float) $a->opening_accumulated_dep, (float) $a->cash_paid]);
    }

    public function test_cash_funded_asset_saves_without_warning(): void
    {
        $this->actingAs($this->owner())->post('/accounting/assets', ['name' => 'Desk', 'acquisition_date' => '2026-11-01', 'in_service_date' => '2026-11-01',
            'cost' => 9000, 'useful_life_months' => 36, 'cash_paid' => 9000, 'cash_payment_date' => '2026-11-01'])
            ->assertSessionHas('toast.type', 'success');
    }

    public function test_liability_with_blank_increase_or_decrease(): void
    {
        $this->actingAs($this->owner())->post('/accounting/liabilities', ['txn_date' => '2026-11-02', 'liability_code' => 'LIA-SIAM-SHARE',
            'description' => 'Siam share paid', 'increase' => null, 'decrease' => 500, 'cash_impact' => -500])->assertSessionHas('toast.type', 'success');
        $this->assertSame(0.0, (float) Liability::query()->where('description', 'Siam share paid')->value('increase'));
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->owner())->post('/system/users', ['name' => 'New Caller', 'email' => 'new@auxtech.local', 'password' => 'Secret123',
            'role_id' => Role::query()->where('name', 'caller')->value('id'), 'is_active' => true])->assertSessionHas('toast.type', 'success');
        $this->assertNotNull(User::query()->where('email', 'new@auxtech.local')->value('email_verified_at'));
        $this->post('/logout');
        $this->post('/login', ['email' => 'new@auxtech.local', 'password' => 'Secret123'])->assertRedirect('/crm');
    }

    public function test_receipt_at_current_time_is_accepted_but_tomorrow_is_not(): void
    {
        $p = Project::query()->where('code', 'PRJ-2610-018')->first();
        $data = ['receivable_type' => 'project', 'receivable_id' => $p->id, 'cash_received' => 100];
        $this->actingAs($this->owner())->post('/accounting/payments', $data + ['paid_at' => '2026-11-02T09:30', 'request_id' => (string) Str::uuid()])
            ->assertSessionHas('toast.type', 'success');
        $this->actingAs($this->owner())->post('/accounting/payments', $data + ['paid_at' => '2026-11-03T00:10', 'request_id' => (string) Str::uuid()])
            ->assertSessionHasErrors('paid_at');
    }

    public function test_employee_name_is_a_string_on_payroll_and_payslip(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->get('/accounting/payroll?month=2026-10')->assertInertia(fn (Assert $p) => $p->where('payrolls.data.0.employee', fn ($v) => is_string($v)));
        $id = Payroll::query()->where('code', 'PAYROLL-202610-abrar')->value('id');
        $this->actingAs($owner)->get("/accounting/payroll/{$id}/payslip")->assertInertia(fn (Assert $p) => $p->where('payroll.data.employee', 'Abrar'));
    }
}
