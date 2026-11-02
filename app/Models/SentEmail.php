<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One email sent (or attempted) from the composer. */
class SentEmail extends BaseModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['content' => 'array', 'sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
