<?php

namespace App\Services\Crm;

use App\Exceptions\Domain\DomainException;
use App\Repositories\Contracts\LeadOutcomeRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lead CSV import with duplicate preview (FR-CRM-15, new).
 * Step 1 `preview()` parses and classifies every row (new / duplicate in CRM / duplicate in file /
 * invalid) and caches the result under a token for 30 minutes. Step 2 `commit()` imports only the
 * rows the user kept, through LeadService (same validation, IDs and derived fields as manual entry).
 */
class LeadImportService
{
    public const COLUMNS = ['phone', 'lead_date', 'business_type', 'industry', 'service', 'source', 'outcome', 'owner', 'quoted_price', 'notes'];

    private const MAX_ROWS = 2000;

    public function __construct(
        private readonly LeadRepositoryInterface $leads,
        private readonly LeadOutcomeRepositoryInterface $outcomes,
        private readonly LeadService $leadService,
        private readonly PhoneNormalizer $phones,
    ) {}

    public function preview(UploadedFile $file, int $userId): array
    {
        $h = fopen($file->getRealPath(), 'r');
        if (! $h) {
            throw new DomainException('Could not read the file.');
        }
        $first = fgets($h);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first); // strip UTF-8 BOM
        $header = array_map(fn ($c) => Str::snake(trim(mb_strtolower($c))), str_getcsv($first));
        if (! in_array('phone', $header, true)) {
            fclose($h);
            throw new DomainException('The first row must contain column names including "phone". Allowed: '.implode(', ', self::COLUMNS).'.');
        }
        $known = $this->outcomes->query()->pluck('name')->all();
        $seen = [];
        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($h)) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw new DomainException('Import is limited to '.self::MAX_ROWS.' rows per file.');
            }
            $data = [];
            foreach ($header as $i => $col) {
                if (in_array($col, self::COLUMNS, true)) {
                    $data[$col] = trim((string) ($cells[$i] ?? ''));
                }
            }
            $norm = $this->phones->normalize($data['phone'] ?? '');
            $issues = [];
            $status = 'new';
            if (! $norm) {
                $status = 'invalid';
                $issues[] = 'Invalid phone';
            } elseif (isset($seen[$norm])) {
                $status = 'duplicate_file';
                $issues[] = 'Same phone as row '.$seen[$norm];
            } elseif ($existing = $this->leads->query()->where('normalized_phone', $norm)->first()) {
                $status = 'duplicate_crm';
                $issues[] = 'Already in CRM: '.$existing->code.' ('.$existing->outcome.')';
            }
            if (! empty($data['outcome']) && ! in_array($data['outcome'], $known, true)) {
                $issues[] = 'Unknown outcome → New';
                $data['outcome'] = 'New';
            }
            if (! empty($data['lead_date']) && strtotime($data['lead_date']) === false) {
                $issues[] = 'Bad date → today';
                $data['lead_date'] = '';
            }
            if ($norm) {
                $seen[$norm] ??= $line;
            }
            $rows[] = ['line' => $line, 'data' => $data, 'normalized' => $norm, 'status' => $status, 'issues' => $issues];
        }
        fclose($h);

        $token = (string) Str::uuid();
        Cache::put("lead-import:{$userId}:{$token}", $rows, now()->addMinutes(30));
        $counts = array_count_values(array_column($rows, 'status'));

        return ['token' => $token, 'rows' => $rows, 'counts' => [
            'total' => count($rows), 'new' => $counts['new'] ?? 0, 'duplicate_crm' => $counts['duplicate_crm'] ?? 0,
            'duplicate_file' => $counts['duplicate_file'] ?? 0, 'invalid' => $counts['invalid'] ?? 0,
        ]];
    }

    /** @param list<int> $lines rows (by CSV line number) the user chose to import; only `new` rows are allowed. */
    public function commit(string $token, int $userId, array $lines): array
    {
        $rows = Cache::get("lead-import:{$userId}:{$token}") ?? throw new DomainException('This preview expired — upload the file again.');
        $wanted = array_flip($lines);
        $created = [];
        DB::transaction(function () use ($rows, $wanted, &$created) {
            foreach ($rows as $r) {
                if (! isset($wanted[$r['line']]) || $r['status'] !== 'new') {
                    continue;
                }
                $data = array_filter($r['data'], fn ($v) => $v !== '' && $v !== null);
                if (isset($data['lead_date'])) {
                    $data['lead_date'] = date('Y-m-d', strtotime($data['lead_date']));
                }
                $data['notes'] = trim(($data['notes'] ?? '')."\nImported from CSV row ".$r['line']);
                $created[] = $this->leadService->create($data)->code;
            }
        });
        Cache::forget("lead-import:{$userId}:{$token}");

        return $created;
    }

    public function template(): string
    {
        return implode(',', self::COLUMNS)."\n01711000000,".now()->toDateString().",E-commerce / Online Store,Fashion / Clothing,Website,Facebook,New,Abrar,25000,Example row\n";
    }
}
