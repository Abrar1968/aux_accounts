<?php

namespace Tests\Feature\System;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Collaborator;
use App\Models\PartnershipRevenue;
use App\Models\Project;
use App\Models\Referral;
use App\Models\Role;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Services\Accounting\CustomerPaymentService;
use App\Services\Accounting\PartnershipService;
use App\Services\System\EmailComposerService;
use App\Support\Period;
use App\Support\PermissionCatalog;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Word-by-word search, tick-box role permissions (no wildcards) and the email address book. */
class SearchRolesEmailTest extends TestCase
{
    use SeedsWorkbook;

    public function test_roles_store_explicit_permissions_and_the_owner_keeps_everything(): void
    {
        foreach (Role::all() as $role) {
            $this->assertEmpty(array_filter($role->permissions, fn ($p) => str_contains($p, '*')), "{$role->name} still has a wildcard");
        }
        $this->assertCount(count(PermissionCatalog::keys()), Role::query()->where('name', 'owner')->first()->permissions);

        $this->actingAs($this->owner())->get('/system/roles')->assertOk()
            ->assertInertia(fn ($p) => $p->component('System/Roles')->has('catalog')->where('roles.0.name', 'owner'));

        $caller = Role::query()->where('name', 'caller')->first();
        $this->put("/system/roles/{$caller->id}", ['permissions' => ['crm.leads.view', 'crm.calls.create']])->assertSessionHas('toast.type', 'success');
        $this->assertSame(['crm.leads.view', 'crm.calls.create'], $caller->fresh()->permissions);
        $this->put("/system/roles/{$caller->id}", ['permissions' => ['crm.*']])->assertSessionHasErrors('permissions.0');
        $this->put("/system/roles/{$caller->id}", ['permissions' => []])->assertSessionHasNoErrors();

        $this->actingAs($this->userWithRole('caller'))->get('/crm/leads')->assertForbidden();
    }

    public function test_search_matches_every_word_in_any_order_and_phone_formats(): void
    {
        $p = Project::query()->firstOrFail();
        $p->update(['customer' => 'Nadia Rahman', 'client_email' => 'nadia@shop.test', 'client_phone' => '01811222333']);
        $projects = app(ProjectRepositoryInterface::class);
        $this->assertTrue($projects->paginate(['q' => 'rahman nadia'])->getCollection()->contains('id', $p->id));
        $this->assertTrue($projects->paginate(['q' => 'shop.test'])->getCollection()->contains('id', $p->id));
        $this->assertTrue($projects->paginate(['q' => '+880 1811-222333'])->getCollection()->contains('id', $p->id));
        $this->assertFalse($projects->paginate(['q' => 'rahman zzz'])->getCollection()->contains('id', $p->id));

        $this->assertGreaterThan(0, app(LeadRepositoryInterface::class)->paginate(['q' => '01768-093784'])->total());

        $this->actingAs($this->owner())->getJson('/ajax/search?q=nadia rahman')->assertOk()
            ->assertJsonFragment(['group' => 'Projects', 'url' => '/accounting/projects/'.$p->id]);
    }

    public function test_email_address_book_lists_project_clients_and_prefills_the_record_client(): void
    {
        $p = Project::query()->firstOrFail();
        $p->update(['client_email' => 'Owner@Client.test']);
        $this->actingAs($this->owner());

        $contacts = $this->getJson('/system/email/contacts')->assertOk()->json('contacts');
        $this->assertContains('owner@client.test', array_column($contacts, 'email'));
        $this->assertContains('admin@auxtech.local', array_column($contacts, 'email'));

        $this->get('/system/email?template=project_update&record=project:'.$p->id, ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'System/Email', 'X-Inertia-Partial-Data' => 'draft', 'X-Inertia-Version' => $this->inertiaVersion()])
            ->assertOk()->assertJsonPath('props.draft.recipients', ['owner@client.test']);
    }

    public function test_email_greets_the_client_not_the_revenue_label(): void
    {
        $this->travelTo('2026-11-14 10:00:00');
        $p = Project::query()->firstOrFail();
        $p->update(['customer' => 'Md . Jahan Mia']);
        $pay = app(CustomerPaymentService::class)->record(['paid_at' => '2026-11-13 10:00', 'receivable_type' => 'project', 'receivable_id' => $p->id, 'cash_received' => 3890]);
        $pay->forceFill(['canonical_name' => 'Organic'])->save();
        $svc = app(EmailComposerService::class);

        $this->assertSame('Dear Md . Jahan Mia,', $svc->draft('payment_receipt', 'payment:'.$pay->id)['greeting']);
        $this->assertSame('Dear Md . Jahan Mia,', $svc->draft('payment_reminder', 'receivable:project:'.$p->id)['greeting']);
        $this->assertSame('Dear Md . Jahan Mia,', $svc->draft('project_update', 'project:'.$p->id)['greeting']);
    }

    public function test_partnership_statement_and_referral_memo_templates(): void
    {
        $this->travelTo('2026-11-14 10:00:00');
        $svc = app(EmailComposerService::class);
        $keys = array_column($svc->templates(), 'key');
        $this->assertContains('partnership_statement', $keys);
        $this->assertContains('referral_memo', $keys);
        $this->assertNotContains('reply', $keys, 'inbox-only template hidden from the composer');

        // partnership: WTBL has Aug–Nov rows once generated; two months earned, 5,000 received
        $col = Collaborator::query()->where('code', 'COL-0004')->firstOrFail();
        $col->update(['contact' => 'Accounts: billing@wtbl.test, +8801700000000']);
        app(PartnershipService::class)->generateThrough(Period::current());
        $rows = PartnershipRevenue::query()->whereIn('assignment_id', $col->assignments()->pluck('id'))->orderBy('earned_month')->get();
        $this->assertCount(4, $rows);
        $rows[0]->update(['direct_amount' => 20000, 'recognized_revenue' => 20000]);
        $rows[1]->update(['direct_amount' => 20000, 'recognized_revenue' => 20000]);
        app(CustomerPaymentService::class)->record(['paid_at' => '2026-11-10 10:00', 'receivable_type' => 'partnership', 'receivable_id' => $rows[0]->id, 'cash_received' => 5000]);

        $this->assertTrue(collect($svc->records('partnership_statement', 'wtbl'))->contains('value', 'collaborator:'.$col->id));
        $d = $svc->draft('partnership_statement', 'collaborator:'.$col->id);
        $det = collect($d['details'])->pluck('value', 'label');
        $this->assertSame(['Dear WTBL,', 'BDT 40,000.00', 'BDT 5,000.00', 'BDT 35,000.00'], [$d['greeting'], $det['Total earned'], $det['Total received'], $det['Balance due']]);
        $this->assertSame(['Aug 2026', 'Maintenance Package', '20,000.00', '5,000.00'], $d['table']['rows'][0]);
        $this->assertSame('Pending', $d['table']['rows'][3][2]);
        $this->assertSame(['billing@wtbl.test'], $d['recipients']);

        // referral memo: Rajib Vai has several referrals → table of all of them
        $ref = Referral::query()->where('referrer', 'Rajib Vai')->firstOrFail();
        $d = $svc->draft('referral_memo', 'referral:'.$ref->id);
        $det = collect($d['details'])->pluck('value', 'label');
        $this->assertSame('Dear Rajib Vai,', $d['greeting']);
        $this->assertSame('BDT '.number_format((float) $ref->memo_amount, 2), explode(' (', $det['Your commission'])[0]);
        $this->assertGreaterThan(1, count($d['table']['rows']));
        $abrar = Referral::query()->where('referrer', 'Abrar')->firstOrFail();
        $this->assertContains('abrar@auxtech.local', $svc->draft('referral_memo', 'referral:'.$abrar->id)['recipients']);
    }

    public function test_referrer_picker_lists_people_most_used_first(): void
    {
        $list = $this->actingAs($this->owner())->getJson('/ajax/referrers')->assertOk()->json('referrers');
        $names = array_column($list, 'name');
        $this->assertSame('Rajib Vai', $names[0], 'most frequent referrer first');
        $this->assertContains('Rizwan', $names);
        $this->assertNotContains('Refferer', $names, 'placeholder values dropped');
        $this->assertSame(count($names), count(array_unique(array_map('mb_strtolower', $names))));
        $this->actingAs($this->userWithRole('caller'))->getJson('/ajax/referrers')->assertForbidden();
    }

    private function inertiaVersion(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(request());
    }
}
