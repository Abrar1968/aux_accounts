<?php

namespace Tests\Feature\Notifications;

use App\Models\Lead;
use App\Models\NotificationPreference;
use App\Models\OperatingExpense;
use App\Models\User;
use App\Notifications\AutomationFailed;
use App\Notifications\DealWon;
use App\Notifications\FollowUpsDue;
use App\Notifications\PaymentRecorded;
use App\Services\Accounting\PaymentPostingService;
use App\Services\Crm\LeadService;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\ReminderService;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use SeedsWorkbook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
    }

    public function test_channels_follow_catalogue_defaults_and_preferences(): void
    {
        $svc = app(NotificationService::class);
        $owner = User::query()->where('email', 'admin@auxtech.local')->first();
        $this->assertSame(['database', 'broadcast', 'mail'], $svc->channelsFor($owner, FollowUpsDue::TYPE));
        $svc->savePreferences($owner, [['type' => FollowUpsDue::TYPE, 'in_app' => true, 'push' => false, 'email' => false]]);
        $this->assertSame(['database'], $svc->channelsFor($owner, FollowUpsDue::TYPE));
        $svc->savePreferences($owner, [['type' => AutomationFailed::TYPE, 'in_app' => false, 'push' => false, 'email' => false]]);
        $this->assertSame(['database'], $svc->channelsFor($owner, AutomationFailed::TYPE), 'locked type keeps in-app');
        $this->assertTrue(NotificationPreference::query()->where('type', AutomationFailed::TYPE)->value('in_app'));
    }

    public function test_payment_posting_notifies_owner_and_accountants(): void
    {
        Notification::fake();
        $acc = $this->userWithRole('accountant');
        $bill = OperatingExpense::query()->create(['code' => 'EXP-N1', 'accrual_month' => '2026-11-01', 'category' => 'Rent', 'description' => 'x', 'amount' => 10]);
        app(PaymentPostingService::class)->post('expense', $bill->id, ['amount' => 10, 'paid_on' => '2026-11-02']);
        Notification::assertSentTo($acc, PaymentRecorded::class, fn ($n) => str_contains($n->message, '৳10.00'));
        Notification::assertSentTo(User::query()->where('email', 'admin@auxtech.local')->first(), PaymentRecorded::class);
    }

    public function test_deal_won_and_follow_up_reminders(): void
    {
        Notification::fake();
        $lead = Lead::query()->where('stage', 'Interested')->first();
        app(LeadService::class)->update($lead, ['outcome' => 'Won', 'deal_value' => 30000]);
        Notification::assertSentTo(User::query()->where('email', 'admin@auxtech.local')->first(), DealWon::class);

        $sent = app(ReminderService::class)->followUpsDue();
        $this->assertGreaterThan(0, $sent);
        Notification::assertSentTo(User::query()->where('email', 'abrar@auxtech.local')->first(), FollowUpsDue::class, fn ($n) => $n->meta['overdue'] > 0);
    }

    public function test_database_notification_payload_and_centre(): void
    {
        $owner = User::query()->where('email', 'admin@auxtech.local')->first();
        app(NotificationService::class)->send(new PaymentRecorded('Customer receipt recorded', 'PAY-1 · ৳5', 'success', '/accounting/payments'), $owner);
        $n = $owner->notifications()->first();
        $this->assertSame(['type', 'title', 'message', 'level', 'url', 'meta'], array_keys($n->data));
        $this->actingAs($owner)->get('/notifications?filter=unread')->assertOk();
        $this->actingAs($owner)->getJson('/ajax/notifications')->assertJsonPath('unread', 1);
        $this->actingAs($owner)->post('/notifications/read-all')->assertSessionHas('toast');
        $this->assertSame(0, $owner->unreadNotifications()->count());
        $this->actingAs($owner)->put('/profile/notifications', ['preferences' => [['type' => 'deal_won', 'in_app' => true, 'push' => true, 'email' => true]]])
            ->assertSessionHas('toast');
    }
}
