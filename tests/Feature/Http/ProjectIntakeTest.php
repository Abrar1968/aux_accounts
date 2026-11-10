<?php

namespace Tests\Feature\Http;

use App\Models\Project;
use App\Models\User;
use App\Services\Accounting\InvoiceService;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Project Intake Form fields: client contact, domain / hosting, discount, project manager. */
class ProjectIntakeTest extends TestCase
{
    use SeedsWorkbook;

    private function intake(array $extra = []): array
    {
        return array_merge([
            'name' => 'appareldrops.shop', 'customer' => 'Sajal Al Numan', 'kind' => 'project', 'start_date' => '2026-11-05', 'est_delivery_date' => '2026-11-08',
            'revenue_method' => 'Point in Time', 'base_value' => 15000, 'discount' => 3000,
            'client_email' => 'Snsajal0@gmail.com', 'client_phone' => '01992672625', 'client_address' => 'Gazipur, Board Bazar, Bangladesh',
            'domain' => 'appareldrops.shop', 'domain_provided_by' => 'client', 'hosting_provided_by' => 'client', 'project_manager' => 'Dhaka Culture',
        ], $extra);
    }

    public function test_intake_fields_are_saved_and_discount_reduces_contract_value(): void
    {
        $this->travelTo('2026-11-10 10:00:00');
        $this->actingAs(User::query()->where('email', 'admin@auxtech.local')->firstOrFail());
        $this->post('/accounting/projects', $this->intake())->assertSessionHas('toast.type', 'success');
        $p = Project::query()->where('name', 'appareldrops.shop')->firstOrFail();

        $this->assertSame(['Snsajal0@gmail.com', '01992672625', 'client', 'client', 'Dhaka Culture'], [$p->client_email, $p->client_phone, $p->domain_provided_by, $p->hosting_provided_by, $p->project_manager]);
        $this->assertSame(12000.0, $p->total_value, 'valuation 15,000 − discount 3,000');

        $inv = app(InvoiceService::class)->invoice('project', $p->id);
        $this->assertSame(12000.0, $inv['total']);
        $this->assertContains('Discount', array_column($inv['lines'], 'description'));

        $this->get("/accounting/projects/{$p->id}/intake")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/Projects/Intake')->where('project.data.total_value', 12000)->where('project.data.client_address', 'Gazipur, Board Bazar, Bangladesh'));

        $this->put("/accounting/projects/{$p->id}", $this->intake(['discount' => 1000, 'hosting_provided_by' => 'auxtech']))->assertSessionHas('toast.type', 'success');
        $this->assertSame([14000.0, 'auxtech'], [$p->fresh()->total_value, $p->fresh()->hosting_provided_by]);
    }

    public function test_invalid_intake_values_are_rejected(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@auxtech.local')->firstOrFail());
        $this->post('/accounting/projects', $this->intake(['client_email' => 'not-an-email', 'domain_provided_by' => 'someone']))
            ->assertSessionHasErrors(['client_email', 'domain_provided_by']);
    }
}
