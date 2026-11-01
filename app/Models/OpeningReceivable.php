<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

class OpeningReceivable extends BaseModel
{
    use Auditable;

    protected $table = 'opening_receivables';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
        ];
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'receivable_id')->where('receivable_type', 'opening');
    }
}
