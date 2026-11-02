<?php

namespace Tests\Feature\Services;

use App\Exceptions\Domain\DomainException;
use App\Models\Project;
use App\Models\Referral;
use App\Models\User;
use App\Services\Accounting\ControlsService;
use App\Services\Accounting\ProjectService;
use App\Services\Accounting\ReferralService;
use App\Support\Period;
use Tests\Concerns\SeedsWorkbook;
use Tests\TestCase;

/** Shared referrals: the tier memo is split equally between every referrer of a project. */
class ReferralSplitTest extends TestCase
{
    use SeedsWorkbook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
    }

    private function project(string $referrer, float $value = 100000): Project
    {
        return app(ProjectService::class)->create(['name' => 'Split '.$referrer, 'kind' => 'project', 'start_date' => '2026-11-01',
            'revenue_method' => 'Point in Time', 'base_value' => $value, 'referrer' => $referrer]);
    }

    public function test_memo_is_split_equally_to_the_paisa(): void
    {
        $svc = app(ReferralService::class);
        $this->assertSame([333.34, 333.33, 333.33], $svc->split(1000, 3));
        $this->assertSame([0.5, 0.5], $svc->split(1, 2));

        $p = $this->project('Rizwan & abrar, RIZWAN ;  Mahbub Vai');
        $this->assertSame('Rizwan, abrar, Mahbub Vai', $p->referrer, 'names are de-duplicated and normalised');
        $memo = $svc->memoFor(100000);
        $rows = Referral::query()->where('project_id', $p->id)->orderBy('id')->get();
        $this->assertSame(['Rizwan', 'abrar', 'Mahbub Vai'], $rows->pluck('referrer')->all());
        $this->assertEqualsWithDelta($memo, $rows->sum('memo_amount'), 0.001, 'shares add up to the full memo');
        $this->assertSame($svc->split($memo, 3), $rows->map(fn ($r) => (float) $r->memo_amount)->all());
    }

    public function test_changing_referrers_resplits_and_paid_referrer_cannot_be_removed(): void
    {
        $p = $this->project('Rizwan, Abrar');
        $svc = app(ProjectService::class);
        $memo = app(ReferralService::class)->memoFor(100000);

        $svc->update($p, ['referrer' => 'Rizwan']);
        $this->assertSame([[$memo], ['Rizwan']], [Referral::query()->where('project_id', $p->id)->pluck('memo_amount')->map(fn ($v) => (float) $v)->all(),
            Referral::query()->where('project_id', $p->id)->pluck('referrer')->all()]);

        $svc->update($p->fresh(), ['referrer' => 'Rizwan, Abrar']);
        $abrar = Referral::query()->where('project_id', $p->id)->where('referrer', 'Abrar')->firstOrFail();
        app(ReferralService::class)->recordPayment($abrar, '2026-11-02', (float) $abrar->memo_amount);

        try {
            $svc->update($p->fresh(), ['referrer' => 'Rizwan']);
            $this->fail('removed a paid referrer');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Abrar has already been paid', $e->getMessage());
        }
        $this->assertSame('Rizwan, Abrar', $p->fresh()->referrer, 'update rolled back');
        $this->assertSame('Paid', app(ReferralService::class)->status($abrar->fresh()));
        $this->assertSame('OK', collect(app(ControlsService::class)->run(Period::of('2026-11-01')))->firstWhere('key', 'referral_sync')['status']);
    }

    public function test_http_flow_and_referrals_page(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@auxtech.local')->firstOrFail());
        $this->post('/accounting/projects', ['name' => 'Http Split', 'kind' => 'project', 'start_date' => '2026-11-01', 'revenue_method' => 'Point in Time',
            'base_value' => 100000, 'referrer' => 'Rizwan, Abrar'])->assertSessionHas('toast.type', 'success');
        $p = Project::query()->where('name', 'Http Split')->firstOrFail();
        $this->get("/accounting/projects/{$p->id}")->assertOk()->assertInertia(fn ($page) => $page->has('referrals', 2));
        $this->get('/accounting/referrals?q=Abrar')->assertOk();
        $this->post('/accounting/projects', ['name' => 'Too many', 'kind' => 'project', 'start_date' => '2026-11-01', 'revenue_method' => 'Point in Time',
            'base_value' => 1000, 'referrer' => implode(',', range('a', 'l'))])->assertSessionHasErrors('referrer');
    }
}
