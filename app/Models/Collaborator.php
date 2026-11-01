<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collaborator extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'collaborators';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'agreement_start' => \App\Casts\DateOnly::class,
            'agreement_end' => \App\Casts\DateOnly::class,
        ];
    }

    public function assignments() { return $this->hasMany(PartnershipAssignment::class); }
}
