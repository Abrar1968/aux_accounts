<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;

class PartnershipAssignment extends BaseModel
{
    use Auditable;

    protected $table = 'partnership_assignments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => DateOnly::class,
            'effective_through' => DateOnly::class,
            'share_percent' => 'decimal:4',
        ];
    }

    public function collaborator()
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function package()
    {
        return $this->belongsTo(PartnershipPackage::class, 'package_id');
    }

    public function revenues()
    {
        return $this->hasMany(PartnershipRevenue::class, 'assignment_id');
    }
}
