<?php

namespace App\Services\Crm;

use App\Exceptions\Domain\DomainException;
use App\Models\CrmReport;
use App\Notifications\CrmReportReady;
use App\Repositories\Contracts\CrmReportRepositoryInterface;
use App\Services\Notifications\NotificationService;
use App\Services\Shared\SettingsService;
use Carbon\CarbonImmutable;

/**
 * exportReport_ / crmSafetyCheck port. Snapshots are immutable and de-duplicated by a SHA-256
 * hash of (version + payload); PDFs are rendered in the browser from the stored payload.
 */
class CrmReportService
{
    public const VERSION = '2.0.0';

    public function __construct(
        private readonly CrmReportRepositoryInterface $reports,
        private readonly AnalyticsService $analytics,
        private readonly SettingsService $settings,
    ) {}

    /** @return array{start:string, end:string, status:string} */
    public function period(string $type, string $day, ?CarbonImmutable $now = null): array
    {
        $launch = (string) $this->settings->get('crm.start');
        if ($day < $launch) {
            throw new DomainException('Reports cannot predate '.$launch);
        }
        $d = CarbonImmutable::parse($day);
        [$start, $end] = match ($type) {
            'Monthly' => [$d->startOfMonth()->toDateString(), $d->endOfMonth()->toDateString()],
            'Yearly' => [$d->startOfYear()->toDateString(), $d->endOfYear()->toDateString()],
            'Daily' => [$day, $day],
            default => throw new DomainException('Unknown report type'),
        };
        $today = ($now ?? CarbonImmutable::now(config('app.timezone')))->toDateString();

        return ['start' => max($start, $launch), 'end' => $end, 'status' => $end < $today ? 'CLOSED' : 'IN_PROGRESS'];
    }

    public function snapshot(string $type, string $day, string $mode = 'Manual', ?CarbonImmutable $now = null): CrmReport
    {
        $p = $this->period($type, $day, $now);
        $payload = $this->analytics->forPeriod($p['start'], $p['end']) + ['type' => $type, 'brand' => $this->settings->get('crm.report_brand')];
        $hash = hash('sha256', self::VERSION.json_encode($payload));
        $existing = $this->reports->query()->where(['type' => $type, 'period_start' => $p['start'], 'period_end' => $p['end'], 'hash' => $hash])->first();
        if ($existing) {
            return $existing;
        }

        $report = $this->reports->create([
            'type' => $type, 'period_start' => $p['start'], 'period_end' => $p['end'], 'status' => $p['status'],
            'hash' => $hash, 'mode' => $mode, 'payload' => $payload,
        ]);
        if ($mode === 'Automatic') { // manual snapshots are opened directly by the person who generated them
            app(NotificationService::class)->send(new CrmReportReady(
                $type.' CRM report ready', $p['start'].' – '.$p['end'].' · '.$payload['new_leads'].' new leads, '.$payload['calls'].' calls, '.$payload['wins'].' won',
                'info', '/crm/reports/'.$report->id,
            ));
        }

        return $report;
    }

    /** Cursor-driven automatic reports: previous days, closed months, closed years. */
    public function runCursors(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $today = $now->toDateString();
        $launch = (string) $this->settings->get('crm.start');
        $made = ['daily' => 0, 'monthly' => 0, 'yearly' => 0];

        $cursor = (string) ($this->settings->get('crm.cursor.daily') ?: CarbonImmutable::parse($launch)->subDay()->toDateString());
        for ($i = 0; $i < 7; $i++) {
            $next = CarbonImmutable::parse($cursor)->addDay()->toDateString();
            if ($next >= $today) {
                break;
            }
            $this->snapshot('Daily', $next, 'Automatic', $now);
            $this->settings->set('crm.cursor.daily', $cursor = $next, 'crm');
            $made['daily']++;
        }

        $month = (string) ($this->settings->get('crm.cursor.monthly') ?: substr($launch, 0, 7));
        for ($i = 0; $i < 2; $i++) {
            $first = CarbonImmutable::parse($month.'-01');
            if ($first->addMonth()->toDateString() > $today) {
                break;
            }
            $this->snapshot('Monthly', $first->endOfMonth()->toDateString(), 'Automatic', $now);
            $this->settings->set('crm.cursor.monthly', $month = $first->addMonth()->format('Y-m'), 'crm');
            $made['monthly']++;
        }

        $year = (int) ($this->settings->get('crm.cursor.yearly') ?: substr($launch, 0, 4));
        if (($year + 1).'-01-01' <= $today) {
            $this->snapshot('Yearly', $year.'-12-31', 'Automatic', $now);
            $this->settings->set('crm.cursor.yearly', $year + 1, 'crm');
            $made['yearly']++;
        }

        return $made;
    }
}
