<?php

namespace App\Services\System;

use App\Exceptions\Domain\DomainException;
use App\Mail\ComposedMail;
use App\Models\CustomerPayment;
use App\Models\Lead;
use App\Models\MarketingService;
use App\Models\OpeningReceivable;
use App\Models\PartnershipRevenue;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\SentEmail;
use App\Models\User;
use App\Repositories\Contracts\SentEmailRepositoryInterface;
use App\Services\Accounting\CustomerPaymentService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\PayrollService;
use App\Services\Accounting\ReceivableService;
use App\Services\Shared\PeriodService;
use App\Services\Shared\SettingsService;
use App\Support\Money;
use App\Support\Period;
use App\Support\Search;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Admin email composer (new feature). Every template pulls live figures from the same services
 * that build the statements, so the numbers in an email always match the app. The user picks a
 * template + record, ticks which details to include, edits the wording and sends via SMTP.
 */
class EmailComposerService
{
    /** key => [label, record source, description] */
    public const TEMPLATES = [
        'payment_receipt' => ['Payment receipt', 'payment', 'Confirm and thank a customer for a payment received'],
        'payment_reminder' => ['Payment reminder', 'receivable', 'Polite reminder of an outstanding balance'],
        'account_statement' => ['Account statement', 'receivable', 'Every invoice and payment with running balance'],
        'project_update' => ['Project confirmation / delivery', 'project', 'Confirm a project, its value, timeline and payments'],
        'payslip' => ['Salary statement', 'payroll', 'Monthly salary breakdown for an employee'],
        'lead_followup' => ['Lead follow-up', 'lead', 'Thank a prospect after a call and confirm next steps'],
        'monthly_summary' => ['Monthly financial summary', 'month', 'Key figures of a month for partners / investors'],
        'custom' => ['Custom message', null, 'Blank professional email with your own detail lines'],
    ];

    public function __construct(
        private readonly SentEmailRepositoryInterface $repo,
        private readonly ReceivableService $receivables,
        private readonly PayrollService $payroll,
        private readonly FinancialStatementService $statements,
        private readonly PeriodService $periods,
        private readonly SettingsService $settings,
    ) {}

    /** @return list<array{key:string,label:string,source:?string,description:string}> */
    public function templates(): array
    {
        return array_map(fn ($k, $t) => ['key' => $k, 'label' => $t[0], 'source' => $t[1], 'description' => $t[2]], array_keys(self::TEMPLATES), self::TEMPLATES);
    }

    /**
     * Record picker options for a template. Values are refs like "payment:12", "receivable:project:3", "month:2026-10".
     *
     * @return list<array{value:string,label:string,meta:string}>
     */
    public function records(string $template, ?string $q = null): array
    {
        $q = trim((string) $q);
        $like = fn ($query, array $cols) => Search::apply($query, $q, $cols);

        return match (self::TEMPLATES[$template][1] ?? null) {
            'payment' => $like(CustomerPayment::query(), ['code', 'canonical_name', 'revenue_code'])->latest('paid_at')->limit(30)->get()
                ->map(fn ($p) => ['value' => 'payment:'.$p->id, 'label' => $p->code.' · '.$p->canonical_name, 'meta' => $this->money($p->cash_received).' · '.substr((string) $p->paid_at, 0, 10)])->all(),
            'receivable' => $this->receivables->options($q === '' ? null : $q)->sortByDesc('ar')->take(30)
                ->map(fn ($r) => ['value' => 'receivable:'.$r['type'].':'.$r['id'], 'label' => $r['name'].' · '.$r['code'], 'meta' => $r['ar'] > 0 ? 'Due '.$this->money($r['ar']) : 'Settled'])->values()->all(),
            'project' => $like(Project::query(), ['code', 'name', 'customer', 'client_email', 'client_phone', 'domain'])->latest('id')->limit(30)->get()
                ->map(fn ($p) => ['value' => 'project:'.$p->id, 'label' => $p->name.' · '.$p->code, 'meta' => $this->money((float) $p->base_value + (float) $p->change_orders)])->all(),
            'payroll' => $like(Payroll::query()->with('employee'), ['code'])->orderByDesc('period')->limit(40)->get()
                ->map(fn ($p) => ['value' => 'payroll:'.$p->id, 'label' => $p->employee?->name.' · '.Period::of($p->period)->label(), 'meta' => $this->money($p->gross_salary)])->all(),
            'lead' => $like(Lead::query(), ['code', 'phone', 'business_type', 'service'])->latest('last_activity_at')->limit(30)->get()
                ->map(fn ($l) => ['value' => 'lead:'.$l->id, 'label' => $l->phone.' · '.($l->business_type ?: 'Lead'), 'meta' => $l->outcome.' · '.$l->code])->all(),
            'month' => collect(array_reverse(Period::range($this->periods->modelStart(), Period::current())))
                ->filter(fn (Period $p) => $q === '' || stripos($p->label(), $q) !== false)
                ->map(fn (Period $p) => ['value' => 'month:'.$p->key(), 'label' => $p->label(), 'meta' => 'Monthly statement'])->values()->all(),
            default => [],
        };
    }

    /**
     * Every address the composer can offer: project client emails, active team members and
     * anyone emailed before. Deduplicated (case-insensitive), newest projects first.
     *
     * @return list<array{email:string,name:string,source:string,meta:string}>
     */
    public function contacts(): array
    {
        $out = [];
        $add = function (?string $email, string $name, string $source, string $meta) use (&$out) {
            foreach (preg_split('/[,;\s]+/', (string) $email) ?: [] as $e) {
                $e = strtolower(trim($e));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) && ! isset($out[$e])) {
                    $out[$e] = ['email' => $e, 'name' => $name, 'source' => $source, 'meta' => $meta];
                }
            }
        };
        Project::query()->whereNotNull('client_email')->where('client_email', '!=', '')->latest('id')
            ->get(['id', 'code', 'name', 'customer', 'client_email'])
            ->each(fn (Project $p) => $add($p->client_email, $p->customer ?: $p->name, 'Project client', $p->code.' · '.$p->name));
        User::query()->where('is_active', true)->orderBy('name')->get(['name', 'email'])
            ->each(fn (User $u) => $add($u->email, $u->name, 'Team', 'Auxtech team'));
        SentEmail::query()->latest('id')->limit(300)->get(['to', 'cc', 'subject'])
            ->each(function (SentEmail $m) use ($add) {
                $add($m->to, Str::before((string) $m->to, '@'), 'Emailed before', Str::limit((string) $m->subject, 60));
                $add($m->cc, Str::before((string) $m->cc, '@'), 'Emailed before', Str::limit((string) $m->subject, 60));
            });

        return array_values($out);
    }

    /**
     * Client addresses linked to a picked record (project → its client; payment / receivable →
     * the project it belongs to, plus any project of the same customer).
     *
     * @return list<string>
     */
    public function recordRecipients(?string $ref): array
    {
        $ref = (string) $ref;
        $project = null;
        $customer = null;
        if (str_starts_with($ref, 'project:')) {
            $project = Project::query()->find((int) Str::after($ref, 'project:'));
        } elseif (str_starts_with($ref, 'payment:')) {
            $pay = CustomerPayment::query()->find((int) Str::after($ref, 'payment:'));
            $project = $pay?->receivable_type === 'project' ? Project::query()->find($pay->receivable_id) : null;
            $customer = $pay?->canonical_name;
        } elseif (str_starts_with($ref, 'receivable:')) {
            [, $type, $id] = array_pad(explode(':', $ref), 3, null);
            $project = $type === 'project' ? Project::query()->find((int) $id) : null;
        }
        $customer ??= $project?->customer;
        $emails = collect([$project?->client_email]);
        if ($customer) {
            $emails = $emails->merge(Project::query()->where('customer', $customer)->whereNotNull('client_email')->pluck('client_email'));
        }

        return $emails->filter()->flatMap(fn ($e) => preg_split('/[,;\s]+/', strtolower((string) $e)))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values()->all();
    }

    /**
     * Auto-generated draft: subject, greeting, intro, selectable detail lines, optional table, closing.
     *
     * @return array<string,mixed>
     */
    public function draft(string $template, ?string $ref = null): array
    {
        if (! isset(self::TEMPLATES[$template])) {
            throw new DomainException('Unknown email template.');
        }
        $company = (string) $this->settings->get('company.name');
        $d = match ($template) {
            'payment_receipt' => $this->paymentReceipt($this->resolve($ref, 'payment')),
            'payment_reminder' => $this->receivableDraft($ref, false),
            'account_statement' => $this->receivableDraft($ref, true),
            'project_update' => $this->projectDraft($this->resolve($ref, 'project')),
            'payslip' => $this->payslipDraft($this->resolve($ref, 'payroll')),
            'lead_followup' => $this->leadDraft($this->resolve($ref, 'lead')),
            'monthly_summary' => $this->monthlyDraft($ref),
            default => ['subject' => '', 'name' => null, 'intro' => '', 'details' => [], 'closing' => 'Please let us know if you have any questions.'],
        };

        return [
            'template' => $template, 'record_ref' => $ref,
            'subject' => $d['subject'] !== '' ? $d['subject'].' · '.$company : '',
            'greeting' => $d['name'] ? 'Dear '.$d['name'].',' : 'Hello,',
            'intro' => $d['intro'],
            'details' => array_map(fn ($x) => ['label' => $x[0], 'value' => (string) $x[1], 'on' => $x[2] ?? true], $d['details']),
            'table' => $d['table'] ?? null,
            'include_table' => isset($d['table']),
            'closing' => $d['closing'],
            'recipients' => $this->recordRecipients($ref),
        ];
    }

    /** Branded HTML exactly as the recipient will see it. */
    public function render(array $data, User $sender): string
    {
        return view('emails.composed', $this->viewData($data, $sender))->render();
    }

    /** Send synchronously so the user gets an immediate success / failure, and log the attempt. */
    public function send(array $data, User $sender): SentEmail
    {
        $to = $this->addresses($data['to']);
        $cc = $this->addresses($data['cc'] ?? '');
        $log = $this->repo->create([
            'user_id' => $sender->id, 'template' => $data['template'], 'record_ref' => $data['record_ref'] ?? null,
            'to' => implode(', ', $to), 'cc' => $cc ? implode(', ', $cc) : null, 'subject' => $data['subject'],
            'content' => array_intersect_key($data, array_flip(['greeting', 'intro', 'details', 'include_table', 'table', 'closing'])), 'status' => 'sending',
        ]);
        try {
            $mail = Mail::to($to);
            if ($cc) {
                $mail->cc($cc);
            }
            $mail->send(new ComposedMail($data['subject'], $this->render($data, $sender), (string) $this->settings->get('company.name')));
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 1000)]);
            report($e);
            throw new DomainException('Email could not be sent: '.Str::limit($e->getMessage(), 160));
        }

        return $log;
    }

    public function history(array $filters = []): LengthAwarePaginator
    {
        return $this->repo->query()->with('user:id,name')
            ->when($filters['q'] ?? null, fn ($q, $s) => Search::apply($q, $s, ['to', 'cc', 'subject', 'template']))
            ->latest('id')->paginate(15)->withQueryString();
    }

    /** @return list<string> */
    public function addresses(string $list): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\s]+/', $list) ?: []))));
    }

    // ------------------------------------------------------------------ templates

    /**
     * The person to greet: the project's client name (intake "Client name"), the marketing
     * client, the legacy customer or the collaborator — not the payment's revenue label.
     */
    private function clientName(string $type, int $id, ?string $fallback): ?string
    {
        $name = match ($type) {
            'project' => Project::query()->whereKey($id)->value('customer'),
            'marketing' => MarketingService::query()->whereKey($id)->value('client'),
            'opening' => OpeningReceivable::query()->whereKey($id)->value('customer'),
            'partnership' => PartnershipRevenue::query()->with('assignment.collaborator')->find($id)?->assignment?->collaborator?->name,
            default => null,
        };
        $name = trim((string) ($name ?: $fallback));

        return $name !== '' ? $name : null;
    }

    private function paymentReceipt(CustomerPayment $p): array
    {
        $type = $p->receivable_type;
        $balance = $this->receivables->statement($type, (int) $p->receivable_id)['closing'];

        return [
            'subject' => 'Payment received — '.$p->code,
            'name' => $this->clientName($type, (int) $p->receivable_id, $p->canonical_name),
            'intro' => "Thank you for your payment. We confirm that we have received the amount below against {$p->revenue_code}.",
            'details' => [
                ['Receipt number', $p->code], ['Date received', $this->date($p->paid_at)],
                ['Amount received', $this->money($p->cash_received)],
                ['Processing fee', $this->money($p->processing_fee), (float) $p->processing_fee > 0],
                ['Total credited to your account', $this->money((float) $p->cash_received + (float) $p->processing_fee), (float) $p->processing_fee > 0],
                ['Payment method', trim(($p->method ?? '').($p->account ? ' · '.$p->account : '')) ?: '—', (bool) ($p->method || $p->account)],
                ['Reference', $p->revenue_code],
                [$balance >= 0 ? 'Remaining balance' : 'Advance with us', $this->money(abs($balance))],
            ],
            'closing' => 'We appreciate your business. Please keep this email as your receipt.',
        ];
    }

    private function receivableDraft(?string $ref, bool $full): array
    {
        [$type, $id] = $this->receivableRef($ref);
        $s = $this->receivables->statement($type, $id);
        $paid = array_filter($s['lines'], fn ($l) => $l['type'] === 'payment');
        $last = $paid ? end($paid)['date'] : null;
        $billed = Money::round($s['opening'] + $s['total_debit']);
        $details = [
            ['Account', $s['customer']], ['Reference', $s['code']], ['Statement date', $this->date($s['as_of'])],
            ['Total billed', $this->money($billed)], ['Total paid', $this->money($s['total_credit'])],
            ['Last payment', $last ? $this->date($last) : 'No payment yet'],
            [$s['closing'] >= 0 ? 'Outstanding balance' : 'Advance with us', $this->money(abs($s['closing']))],
        ];
        // 4 columns so it fits a phone-width email; payments are shown as negative amounts
        $n = fn (float $v) => number_format($v, 2);
        $table = ['columns' => ['Date', 'Description', 'Amount (BDT)', 'Balance (BDT)'],
            'rows' => array_merge($s['opening'] != 0.0 ? [['', 'Opening balance', '', $n($s['opening'])]] : [], array_map(fn ($l) => [
                $this->date($l['date']), $l['description'].' · '.$l['reference'],
                $n($l['debit'] ?: -$l['credit']), $n($l['balance']),
            ], $s['lines']))];

        return $full ? [
            'subject' => 'Account statement — '.$s['code'],
            'name' => $this->clientName($type, $id, $s['customer']),
            'intro' => 'Please find below your account statement as of '.$this->date($s['as_of']).', listing every charge and payment on this account.',
            'details' => $details, 'table' => $table,
            'closing' => 'If any entry does not match your records, please reply to this email and we will reconcile it promptly.',
        ] : [
            'subject' => 'Friendly payment reminder — '.$s['code'],
            'name' => $this->clientName($type, $id, $s['customer']),
            'intro' => $s['closing'] > 0
                ? 'This is a friendly reminder that a balance of '.$this->money($s['closing'])." remains outstanding on your account ({$s['code']}). We would be grateful if you could arrange payment at your earliest convenience."
                : "Thank you — your account ({$s['code']}) is fully settled. No payment is due at this time.",
            'details' => $details, 'table' => $table,
            'closing' => 'If you have already made this payment, please disregard this reminder and accept our thanks. For any questions, simply reply to this email.',
        ];
    }

    private function projectDraft(Project $p): array
    {
        $s = $this->receivables->statement('project', $p->id);
        $value = $p->total_value;
        $delivered = $p->actual_delivery_date !== null;

        return [
            'subject' => ($delivered ? 'Project delivered — ' : 'Project confirmation — ').$p->name,
            'name' => $p->customer ?: $p->name,
            'intro' => $delivered
                ? "We are pleased to confirm that \"{$p->name}\" was delivered on ".$this->date($p->actual_delivery_date).'. Thank you for trusting us with this work.'
                : "Thank you for choosing us. This email confirms the details of your project \"{$p->name}\".",
            'details' => [
                ['Project', $p->name], ['Project ID', $p->code], ['Contract value', $this->money($value)],
                ['Change orders', $this->money($p->change_orders), (float) $p->change_orders > 0],
                ['Start date', $this->date($p->start_date), (bool) $p->start_date],
                ['Estimated delivery', $this->date($p->est_delivery_date), (bool) $p->est_delivery_date && ! $delivered],
                ['Delivered on', $this->date($p->actual_delivery_date), $delivered],
                ['Amount received', $this->money($s['total_credit'])],
                [$s['closing'] >= 0 ? 'Balance due' : 'Advance with us', $this->money(abs($delivered ? $s['closing'] : $value - $s['total_credit']))],
            ],
            'closing' => $delivered ? 'We would love to hear your feedback, and we are here for any support you need.' : 'We will keep you updated at every milestone. Please reply if anything needs to change.',
        ];
    }

    private function payslipDraft(Payroll $pr): array
    {
        $sum = $this->payroll->summary($pr);
        $month = Period::of($pr->period)->label();

        return [
            'subject' => "Salary statement — {$month}",
            'name' => $pr->employee?->name,
            'intro' => "Please find below your salary statement for {$month}.",
            'details' => [
                ['Employee', $pr->employee?->name], ['Designation', $pr->employee?->designation ?: '—', (bool) $pr->employee?->designation],
                ['Salary month', $month], ['Payroll ID', $pr->code], ['Gross salary', $this->money($pr->gross_salary)],
                ['Advance recovered', $this->money($sum['advance_recovery']), $sum['advance_recovery'] > 0],
                ['Salary paid', $this->money($sum['salary_cash_settled'])],
                ['Referral commission paid', $this->money($sum['referral_paid']), $sum['referral_paid'] > 0],
                ['Unpaid balance', $this->money($sum['unpaid'])], ['Status', $sum['status']],
            ],
            'closing' => 'Thank you for your contribution this month. Please contact the accounts team if anything looks incorrect.',
        ];
    }

    private function leadDraft(Lead $l): array
    {
        $service = $l->service ?: 'our services';

        return [
            'subject' => 'Thank you for your time — '.($l->service ?: 'next steps'),
            'name' => null,
            'intro' => "Thank you for speaking with us about {$service}. As discussed, here is a short summary and the next steps.",
            'details' => [
                ['Service', $l->service ?: '—', (bool) $l->service], ['Business type', $l->business_type ?: '—', (bool) $l->business_type],
                ['Quoted price', $this->money($l->quoted_price), (float) $l->quoted_price > 0],
                ['Meeting', $l->meeting_at ? $l->meeting_at->timezone(config('app.timezone'))->format('d M Y, g:i a').($l->meeting_details ? ' · '.$l->meeting_details : '') : '—', (bool) $l->meeting_at],
                ['Next follow-up', $l->next_followup ? $this->date($l->next_followup) : '—', (bool) $l->next_followup],
                ['Our reference', $l->code, false],
            ],
            'closing' => 'Please reply to this email or call us if you have any questions. We look forward to working with you.',
        ];
    }

    private function monthlyDraft(?string $ref): array
    {
        $p = Period::tryOf(Str::after((string) $ref, 'month:')) ?? throw new DomainException('Select a month.');
        $s = $this->statements->forPeriod($p);

        return [
            'subject' => 'Monthly financial summary — '.$p->label(),
            'name' => null,
            'intro' => 'Please find below the key financial figures for '.$p->label().', taken from the monthly statements.',
            'details' => [
                ['Total revenue', $this->money($s['total_revenue'])], ['Total expenses', $this->money($s['total_expenses'])],
                ['Net income / (loss)', $this->money($s['net_income'])], ['Customer cash receipts', $this->money($s['customer_receipts'])],
                ['Total cash out', $this->money($s['total_cash_out']), false], ['Ending cash / (overdraft)', $this->money($s['ending_cash'])],
                ['Accounts receivable', $this->money($s['accounts_receivable'])], ['Total assets', $this->money($s['total_assets']), false],
                ['Total liabilities', $this->money($s['total_liabilities']), false], ['Total equity', $this->money($s['total_equity']), false],
            ],
            'closing' => 'The full statements are available in Auxtech Accounts. Please reply with any questions.',
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function resolve(?string $ref, string $kind): mixed
    {
        $id = (int) Str::after((string) $ref, $kind.':');
        $class = ['payment' => CustomerPayment::class, 'project' => Project::class, 'payroll' => Payroll::class, 'lead' => Lead::class][$kind];
        if (! str_starts_with((string) $ref, $kind.':') || ! ($m = $class::query()->find($id))) {
            throw new DomainException('Select a record for this template.');
        }

        return $m;
    }

    /** @return array{0:string,1:int} */
    private function receivableRef(?string $ref): array
    {
        $parts = explode(':', (string) $ref);
        if (count($parts) !== 3 || $parts[0] !== 'receivable' || ! isset(CustomerPaymentService::MODELS[$parts[1]])) {
            throw new DomainException('Select a customer account for this template.');
        }

        return [$parts[1], (int) $parts[2]];
    }

    private function viewData(array $data, User $sender): array
    {
        return [
            'subject' => $data['subject'], 'greeting' => $data['greeting'] ?? '', 'intro' => $data['intro'] ?? '',
            'details' => array_values(array_filter($data['details'] ?? [], fn ($d) => ! empty($d['on']) && trim((string) ($d['label'] ?? '')) !== '')),
            'table' => ! empty($data['include_table']) ? ($data['table'] ?? null) : null,
            'closing' => $data['closing'] ?? '',
            // every email is signed by the same person, whoever clicks Send (Settings → Policies → Email signature)
            'sender' => $this->settings->get('company.signature_name') ?: $sender->name, 'title' => $this->settings->get('company.signature_title'),
            'company' => $this->settings->get('company.name'), 'website' => $this->settings->get('company.website'),
            'phone' => $this->settings->get('company.phone'), 'address' => $this->settings->get('company.address'),
            'replyTo' => config('mail.from.address'),
            'logo' => app(BrandingService::class)->emailLogoUrl(),
        ];
    }

    private function money(mixed $v): string
    {
        $v = (float) $v;

        return ($v < 0 ? '-' : '').'BDT '.number_format(abs($v), 2);
    }

    private function date(mixed $v): string
    {
        return $v ? CarbonImmutable::parse($v)->format('d M Y') : '—';
    }
}
