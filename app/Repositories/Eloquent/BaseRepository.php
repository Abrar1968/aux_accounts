<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    /** @var class-string<Model> */
    protected string $model;

    /** Columns searched by the `q` filter. */
    protected array $searchable = ['code'];

    /** Equality filters: query-string key => column (month values are normalised to the 1st). */
    protected array $filterable = [];

    /** Default ordering for listings. */
    protected array $orderBy = ['id', 'desc'];

    public function query(): Builder
    {
        return ($this->model)::query();
    }

    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function findByCode(string $code): ?Model
    {
        return $this->query()->where('code', $code)->first();
    }

    public function all(): Collection
    {
        return $this->query()->orderBy(...$this->orderBy)->get();
    }

    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model->refresh();
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = $this->query();
        $this->applyFilters($q, $filters);
        $sort = $filters['sort'] ?? null;
        if (is_string($sort) && preg_match('/^-?[a-z_]+$/', $sort)) {
            $q->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');
        } else {
            $q->orderBy(...$this->orderBy);
        }

        return $q->paginate(min(max($perPage, 1), 200))->withQueryString();
    }

    protected function applyFilters(Builder $q, array $filters): void
    {
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
            $q->where(function (Builder $w) use ($term) {
                foreach ($this->searchable as $col) {
                    $w->orWhere($col, 'like', $term);
                }
            });
        }
        foreach ($this->filterable as $key => $col) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $q->where($col, $key === 'month' ? \App\Support\Period::of($filters[$key])->date() : $filters[$key]);
            }
        }
        foreach ($this->periodFilterColumns() as $col) {
            if (! empty($filters['from'])) {
                $q->where($col, '>=', $filters['from']);
            }
            if (! empty($filters['to'])) {
                $q->where($col, '<=', $filters['to']);
            }
        }
    }

    /** Date column(s) used by from/to filters. */
    protected function periodFilterColumns(): array
    {
        return [];
    }

    public function sumBetween(string $column, string $dateColumn, string $from, string $to, array $wheres = []): float
    {
        $q = $this->query()->where($dateColumn, '>=', $from)->where($dateColumn, '<', $to);
        $this->applyWheres($q, $wheres);

        return Money::round($q->sum($column));
    }

    public function sumBefore(string $column, string $dateColumn, string $cut, array $wheres = []): float
    {
        $q = $this->query()->whereNotNull($dateColumn)->where($dateColumn, '<', $cut);
        $this->applyWheres($q, $wheres);

        return Money::round($q->sum($column));
    }

    protected function applyWheres(Builder $q, array $wheres): void
    {
        foreach ($wheres as $key => $value) {
            if (is_array($value)) {
                $q->where($key, $value[0], $value[1]);
            } else {
                $q->where($key, $value);
            }
        }
    }
}
