<?php

namespace App\Services\Accounting;

use App\Models\Project;
use App\Models\RevenueRecognition;
use App\Repositories\Contracts\RevenueRecognitionRepositoryInterface;
use App\Services\Accounting\Engine\RecognitionCalculator;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SequenceService;
use App\Services\Shared\SettingsService;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

/**
 * Revenue_Recognition ledger: saves rows and keeps every derived column consistent.
 */
class RevenueRecognitionService
{
    public function __construct(
        private readonly RevenueRecognitionRepositoryInterface $repo,
        private readonly RecognitionCalculator $calculator,
        private readonly SettingsService $settings,
        private readonly SequenceService $sequences,
        private readonly PeriodService $periods,
    ) {}

    /** @param array{period:string, project_id:int, ending_progress?:?float, override_amount?:mixed, notes?:?string} $data */
    public function create(array $data, bool $auto = false): RevenueRecognition
    {
        $this->periods->assertOpen($data['period']);

        return DB::transaction(function () use ($data, $auto) {
            $p = Period::of($data['period']);
            $n = $this->sequences->next('RR');
            $row = $this->repo->create([
                'code' => 'RR-'.$p->start->format('Ym').'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'period' => $data['period'],
                'project_id' => $data['project_id'],
                'ending_progress' => $data['ending_progress'] ?? null,
                'override_amount' => $data['override_amount'] ?? null,
                'notes' => $data['notes'] ?? null,
                'auto_generated' => $auto,
                'recognition_month' => $p->date(),
            ]);

            $this->recalculateProject($row->project);

            return $row->fresh();
        });
    }

    public function update(RevenueRecognition $row, array $data): RevenueRecognition
    {
        $this->periods->assertOpen($row->period, $data['period'] ?? null);
        $this->repo->update($row, array_intersect_key($data, array_flip(['period', 'ending_progress', 'override_amount', 'notes'])));
        $this->recalculateProject($row->project);

        return $row->fresh();
    }

    public function delete(RevenueRecognition $row): void
    {
        $this->periods->assertOpen($row->period);
        $project = $row->project;
        $this->repo->delete($row);
        $this->recalculateProject($project);
    }

    /** Recomputes every row of a project in date order (opening progress depends on earlier rows). */
    public function recalculateProject(Project $project): ?RevenueRecognition
    {
        $rows = $this->repo->query()->where('project_id', $project->id)->orderBy('period')->orderBy('id')->get();
        $siam = [
            'start' => (string) $this->settings->get('siam.start'),
            'months' => (int) $this->settings->get('siam.months'),
            'share' => (float) $this->settings->get('siam.share'),
        ];
        $last = null;
        foreach ($rows as $row) {
            $month = Period::of($row->period);
            $opening = (float) $rows->filter(fn ($r) => $r->period->toDateString() < $month->date() && $r->ending_progress !== null)
                ->max('ending_progress');
            $duplicate = ! $row->is_historical && $rows->filter(fn ($r) => ! $r->is_historical && $r->id !== $row->id
                && Period::of($r->period)->equals($month))->isNotEmpty();
            $contract = $row->is_historical ? (float) $row->contract_value : $project->total_value;
            $calc = $this->calculator->compute([
                'has_project' => true,
                'period' => $row->period->toDateString(),
                'project_known' => true,
                'method' => $project->revenue_method,
                'project_type' => $project->typeLabel(),
                'contract_value' => $contract,
                'opening_progress' => $opening,
                'ending_progress' => $row->ending_progress !== null ? (float) $row->ending_progress : null,
                'override' => $row->override_amount,
                'duplicate_in_month' => $duplicate,
                'historical' => (bool) $row->is_historical,
            ], $siam);
            $row->fill($calc + [
                'opening_progress' => $row->is_historical ? $row->opening_progress : $opening,
                'contract_value' => $contract,
                'method_label' => $row->method_label ?: $project->revenue_method,
            ])->saveQuietly();
            $last = $row;
        }

        return $last;
    }

    /**
     * Code.gs addProgressRows_ port. Point-in-Time projects delivered on/before the month get one
     * automatic recognition row; Over-Time projects get one progress draft per month.
     *
     * @return array{point_in_time:int, over_time:int}
     */
    public function addProgressRows(Period $month): array
    {
        $created = ['point_in_time' => 0, 'over_time' => 0];
        $projects = Project::query()->where('kind', '!=', 'lead')->where('revenue_method', '!=', 'Lead / No Revenue')->get();
        foreach ($projects as $project) {
            $existing = $this->repo->query()->where('project_id', $project->id)->get();
            if ($project->revenue_method === 'Over Time') {
                if ($existing->contains(fn ($r) => Period::of($r->period)->equals($month))) {
                    continue;
                }
                $start = Period::tryOf($project->start_date);
                if (! $start || $month->lt($start)) {
                    continue;
                }
                if ($project->actual_delivery_date && Period::of($project->actual_delivery_date)->lt($month)) {
                    continue;
                }
                if ($project->total_value <= 0) {
                    continue; // surfaced by ControlsService as "Contract value missing"
                }
                if ($this->periods->isLocked($month->date())) {
                    continue;
                }
                $this->create(['period' => $month->date(), 'project_id' => $project->id,
                    'notes' => 'Enter ending cumulative progress; earned revenue waits for valid progress.'], true);
                $created['over_time']++;

                continue;
            }
            if (! $project->actual_delivery_date || $existing->isNotEmpty()) {
                continue;
            }
            $delivery = Period::of($project->actual_delivery_date);
            if ($month->lt($delivery) || $project->total_value <= 0 || $this->periods->isLocked($project->actual_delivery_date)) {
                continue;
            }
            $this->create([
                'period' => $project->actual_delivery_date->toDateString(),
                'project_id' => $project->id,
                'override_amount' => $project->total_value,
                'notes' => 'Automatic delivery recognition: '.$project->actual_delivery_date->format('d-M-Y'),
            ], true);
            $created['point_in_time']++;
        }

        return $created;
    }
}
