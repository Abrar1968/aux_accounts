<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'projects';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source_month' => \App\Casts\DateOnly::class,
            'start_date' => \App\Casts\DateOnly::class,
            'est_delivery_date' => \App\Casts\DateOnly::class,
            'actual_delivery_date' => \App\Casts\DateOnly::class,
            'base_value' => 'decimal:2',
            'change_orders' => 'decimal:2',
        ];
    }

    use \Illuminate\Database\Eloquent\SoftDeletes;

    public function recognitions() { return $this->hasMany(RevenueRecognition::class); }

    public function payments() { return $this->hasMany(CustomerPayment::class, 'receivable_id')->where('receivable_type', 'project'); }

    public function referral() { return $this->hasOne(Referral::class); }

    public function lead() { return $this->belongsTo(Lead::class); }

    public function getTotalValueAttribute(): float { return round((float) $this->base_value + (float) $this->change_orders, 2); }

    public function isLead(): bool { return $this->kind === 'lead' || $this->revenue_method === 'Lead / No Revenue'; }

    public function typeLabel(): string { return match ($this->kind) { 'international' => 'International Project', 'lead' => 'Lead', default => 'Project' }; }
}
