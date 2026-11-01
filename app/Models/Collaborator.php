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
            'agreement_start' => 'date',
            'agreement_end' => 'date',
        ];
    }

    public function assignments() { return $this->hasMany(PartnershipAssignment::class); }
}
