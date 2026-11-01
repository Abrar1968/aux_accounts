<?php

namespace App\Models;

class Attachment extends BaseModel
{
    protected $table = 'attachments';

    protected $guarded = ['id'];

    public function attachable()
    {
        return $this->morphTo();
    }
}
