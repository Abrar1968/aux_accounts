<?php

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;

class SettingRepository extends BaseRepository implements SettingRepositoryInterface
{
    protected string $model = Setting::class;

    protected array $searchable = ['key'];

    protected function periodFilterColumns(): array
    {
        return [];
    }
}
