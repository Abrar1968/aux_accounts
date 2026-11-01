<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnershipAssignment extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'partnership_assignments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => \App\Casts\DateOnly::class,
            'effective_through' => \App\Casts\DateOnly::class,
            'share_percent' => 'decimal:4',
        ];
    }

    public function collaborator() { return $this->belongsTo(Collaborator::class); }

    public function package() { return $this->belongsTo(PartnershipPackage::class, 'package_id'); }

    public function revenues() { return $this->hasMany(PartnershipRevenue::class, 'assignment_id'); }
}
