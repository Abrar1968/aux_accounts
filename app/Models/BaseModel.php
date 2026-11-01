<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use SerializesLocalDates;
}
