<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function query(): Builder;

    public function find(int $id): ?Model;

    public function findOrFail(int $id): Model;

    public function findByCode(string $code): ?Model;

    public function all(): Collection;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): void;

    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /** SUM(column) where dateColumn in [from, to) plus extra equality / operator wheres. */
    public function sumBetween(string $column, string $dateColumn, string $from, string $to, array $wheres = []): float;

    /** SUM(column) where dateColumn < cut. */
    public function sumBefore(string $column, string $dateColumn, string $cut, array $wheres = []): float;
}
