<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends BaseModel
{
    use Auditable;

    protected $table = 'projects';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source_month' => DateOnly::class,
            'start_date' => DateOnly::class,
            'est_delivery_date' => DateOnly::class,
            'actual_delivery_date' => DateOnly::class,
            'client_signature' => 'array',
            'discount' => 'decimal:2',
            'base_value' => 'decimal:2',
            'change_orders' => 'decimal:2',
        ];
    }

    use SoftDeletes;

    public function recognitions()
    {
        return $this->hasMany(RevenueRecognition::class);
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'receivable_id')->where('receivable_type', 'project');
    }

    /** One row per referrer; the tier memo is split equally between them. */
    public function referrals()
    {
        return $this->hasMany(Referral::class)->orderBy('id');
    }

    /**
     * Referrer names from the comma-separated field ("Rizwan, Abrar" or "Rizwan & Abrar").
     *
     * @return list<string>
     */
    public function referrerNames(): array
    {
        return self::parseReferrers($this->referrer);
    }

    /** @return list<string> */
    public static function parseReferrers(?string $value): array
    {
        $names = array_map(fn ($n) => preg_replace('/\s+/', ' ', trim($n)), preg_split('/[,;&+\/]+/', (string) $value) ?: []);
        $out = [];
        foreach (array_filter($names, fn ($n) => $n !== '') as $n) {
            if (! in_array(mb_strtolower($n), array_map('mb_strtolower', $out), true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /** Contract value = project valuation + change orders − discount (Project Intake Form). */
    public function getTotalValueAttribute(): float
    {
        return round((float) $this->base_value + (float) $this->change_orders - (float) $this->discount, 2);
    }

    public function isLead(): bool
    {
        return $this->kind === 'lead' || $this->revenue_method === 'Lead / No Revenue';
    }

    public function typeLabel(): string
    {
        return match ($this->kind) {
            'international' => 'International Project', 'lead' => 'Lead', default => 'Project'
        };
    }
}
