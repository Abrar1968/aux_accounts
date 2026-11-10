<?php

namespace Tests\Feature\System;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Project;
use App\Models\Role;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
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

    private function inertiaVersion(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(request());
    }
}
