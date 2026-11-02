<?php

namespace App\Repositories\Eloquent;

use App\Models\SentEmail;
use App\Repositories\Contracts\SentEmailRepositoryInterface;

class SentEmailRepository extends BaseRepository implements SentEmailRepositoryInterface
{
    protected string $model = SentEmail::class;

    protected array $searchable = ['to', 'subject', 'template'];
}
