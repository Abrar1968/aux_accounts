<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Collaborator extends Model
{
    use Auditable;

    protected $table = 'collaborators';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'agreement_start' => DateOnly::class,
            'agreement_end' => DateOnly::class,
        ];
    }

    public function assignments()
    {
        return $this->hasMany(PartnershipAssignment::class);
    }
}
