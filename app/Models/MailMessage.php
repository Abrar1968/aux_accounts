<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A message read from the company mailbox (Inbox). */
class MailMessage extends BaseModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'read_at' => 'datetime', 'replied_at' => 'datetime', 'archived_at' => 'datetime', 'has_attachments' => 'boolean'];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class);
    }

    public function sentEmail(): BelongsTo
    {
        return $this->belongsTo(SentEmail::class);
    }
}
