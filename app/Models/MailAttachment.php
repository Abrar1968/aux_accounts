<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** File attached to an inbox message (stored under storage/app/private/mail). */
class MailAttachment extends BaseModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['inline' => 'boolean'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }
}
