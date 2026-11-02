<?php

namespace Tests\Feature\Http;

use App\Models\Lead;
use App\Models\OperatingExpense;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

class AuthAndPermissionsTest extends TestCase
{
    use SeedsWorkbook;

    public function test_guest_is_redirected_and_can_sign_in(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => 'admin@auxtech.local', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'admin@auxtech.local', 'password' => env('ADMIN_PASSWORD', 'ChangeMe!2026')])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_is_throttled_and_inactive_users_rejected(): void
    {
        $u = $this->owner();
        $u->update(['is_active' => false]);
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'x@y.z', 'password' => 'bad']);
        }
        $this->post('/login', ['email' => 'x@y.z', 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many attempts', session('errors')->first('email'));
    }

    public function test_password_reset_sends_mail_and_resets(): void
    {
        Notification::fake();
        $user = User::query()->where('email', 'admin@auxtech.local')->first();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('toast');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'NewPass123'])->assertRedirect('/');
    }

    public function test_caller_permissions_and_own_leads_only(): void
    {
        $caller = User::query()->where('name', 'Abrar')->where('email', 'abrar@auxtech.local')->first();
        $this->actingAs($caller)->get('/')->assertForbidden();
        $this->actingAs($caller)->get('/accounting/expenses')->assertForbidden();
        $this->actingAs($caller)->get('/crm')->assertOk();
        $this->actingAs($caller)->post('/login')->assertRedirect(); // guest-only route
        $mine = Lead::query()->where('owner', 'Abrar')->first();
        $this->actingAs($caller)->get('/crm/leads/'.$mine->id)->assertOk();
        $other = Lead::query()->create(['code' => 'X-1', 'lead_date' => '2026-11-01', 'phone' => '01799999999', 'owner' => 'Rizwan']);
        $this->actingAs($caller)->get('/crm/leads/'.$other->id)->assertForbidden();
    }

    public function test_viewer_cannot_write_and_accountant_cannot_reopen(): void
    {
        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)->get('/accounting/statements/2026-10')->assertOk();
        $this->actingAs($viewer)->post('/accounting/expenses', [])->assertForbidden();
        $acc = $this->userWithRole('accountant');
        $this->actingAs($acc)->post('/accounting/periods/2026-10/close')->assertRedirect();
        $this->actingAs($acc)->post('/accounting/periods/2026-10/reopen', ['reason' => 'fix entries'])->assertForbidden();
    }

    public function test_write_flow_with_toast_validation_and_period_lock(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        $owner = $this->owner();
        $this->actingAs($owner)->post('/accounting/expenses', ['expense_date' => '2026-11-02', 'category' => 'Office & Admin', 'description' => 'Tea', 'amount' => 250])
            ->assertSessionHas('toast.type', 'success');
        $bill = OperatingExpense::query()->where('description', 'Tea')->firstOrFail();
        $this->assertSame('EXP-110', $bill->code);
        $req = (string) Str::uuid();
        $this->actingAs($owner)->post("/accounting/expenses/{$bill->id}/payments", ['amount' => 250, 'paid_on' => '2026-11-02', 'request_id' => $req])
            ->assertSessionHas('toast.type', 'success');
        $this->actingAs($owner)->post("/accounting/expenses/{$bill->id}/payments", ['amount' => 5, 'paid_on' => '2026-11-02', 'request_id' => (string) Str::uuid()])
            ->assertSessionHas('toast.type', 'error');
        $this->actingAs($owner)->post('/accounting/expenses', ['amount' => -5])->assertSessionHasErrors(['expense_date', 'category', 'amount']);

        $this->actingAs($owner)->post('/accounting/periods/2026-11/close')->assertSessionHas('toast.type', 'success');
        $this->actingAs($owner)->post('/accounting/expenses', ['expense_date' => '2026-11-02', 'category' => 'Rent', 'description' => 'late', 'amount' => 1])
            ->assertSessionHas('toast.message', 'Period Nov-2026 is closed. Ask an admin to reopen it.');
        $this->actingAs($owner)->post('/accounting/periods/2026-11/reopen', ['reason' => 'late bill'])->assertSessionHas('toast');
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'Period', 'action' => 'updated']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'ExpensePayment', 'action' => 'created', 'user_id' => $owner->id]);
    }
}
