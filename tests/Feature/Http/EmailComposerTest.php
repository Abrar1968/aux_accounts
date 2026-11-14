<?php

namespace Tests\Feature\Http;

use App\Mail\ComposedMail;
use App\Models\CustomerPayment;
use App\Models\SentEmail;
use App\Models\User;
use App\Services\System\EmailComposerService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

class EmailComposerTest extends TestCase
{
    use SeedsWorkbook;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
        $this->owner = User::query()->where('email', 'admin@auxtech.local')->firstOrFail();
        $this->actingAs($this->owner);
    }

    public function test_every_template_generates_a_draft_from_a_real_record(): void
    {
        $svc = app(EmailComposerService::class);
        foreach (EmailComposerService::TEMPLATES as $key => [$label, $source]) {
            $ref = null;
            if ($source) {
                $options = $svc->records($key);
                $this->assertNotEmpty($options, "$key has no records");
                $ref = $options[0]['value'];
            }
            $d = $svc->draft($key, $ref);
            if (! in_array($key, ['custom', 'reply'], true)) {
                $this->assertStringContainsString('· Auxtech', $d['subject'], $key);
                $this->assertStringNotContainsString('Software', $d['subject'], $key);
                $this->assertNotEmpty($d['details'], $key);
                $this->assertNotSame('', $d['intro'], $key);
            }
            $html = $svc->render($d + ['to' => 'x@example.com'], $this->owner);
            $this->assertStringContainsString('Kind regards', $html, $key);
        }
    }

    public function test_receipt_figures_match_the_ledger(): void
    {
        $p = CustomerPayment::query()->where('processing_fee', 0)->firstOrFail();
        $d = app(EmailComposerService::class)->draft('payment_receipt', 'payment:'.$p->id);
        $values = collect($d['details'])->pluck('value', 'label');
        $this->assertSame('BDT '.number_format((float) $p->cash_received, 2), $values['Amount received']);
        $this->assertSame($p->code, $values['Receipt number']);
    }

    public function test_send_delivers_only_ticked_details_and_logs(): void
    {
        Mail::fake();
        $d = app(EmailComposerService::class)->draft('monthly_summary', 'month:2026-10');
        $d['details'][0]['on'] = false; // untick "Total revenue"
        $this->post('/system/email', $d + ['to' => 'partner@example.com, investor@example.com', 'cc' => 'cfo@example.com'])
            ->assertSessionHas('toast.type', 'success');

        Mail::assertSent(ComposedMail::class, function (ComposedMail $m) {
            return $m->hasTo('partner@example.com') && $m->hasTo('investor@example.com') && $m->hasCc('cfo@example.com')
                && str_contains($m->body, 'Net income') && ! str_contains($m->body, 'Total revenue');
        });
        $log = SentEmail::query()->latest('id')->firstOrFail();
        $this->assertSame(['sent', 'monthly_summary', 'partner@example.com, investor@example.com'], [$log->status, $log->template, $log->to]);
    }

    public function test_invalid_recipient_is_rejected(): void
    {
        Mail::fake();
        $this->post('/system/email', ['template' => 'custom', 'to' => 'not-an-email', 'subject' => 'Hi', 'intro' => 'Hello'])
            ->assertSessionHasErrors('to');
        Mail::assertNothingSent();
    }

    public function test_smtp_failure_is_logged_and_reported(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $this->post('/system/email', ['template' => 'custom', 'to' => 'a@example.com', 'subject' => 'Hi', 'intro' => 'Hello'])
            ->assertSessionHas('toast.type', 'error');
        $this->assertSame('failed', SentEmail::query()->latest('id')->value('status'));
    }

    public function test_page_preview_and_permissions(): void
    {
        $this->get('/system/email')->assertOk();
        $this->get('/system/email?template=payment_reminder&record=receivable:opening:1')->assertOk();
        $this->post('/system/email/preview', ['template' => 'custom', 'subject' => 'Hello there', 'intro' => 'Body text', 'details' => [['label' => 'Invoice', 'value' => 'INV-1', 'on' => true]]])
            ->assertOk()->assertSee('INV-1')->assertSee('Body text');

        $this->actingAs($this->userWithRole('caller'))->get('/system/email')->assertForbidden();
        $this->actingAs($this->userWithRole('accountant'))->get('/system/email')->assertOk();
    }
}
