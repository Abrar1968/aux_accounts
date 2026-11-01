<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;

class EmployeeService
{
    public function __construct(private readonly EmployeeRepositoryInterface $repo) {}

    public function create(array $data): Employee
    {
        $slug = mb_strtolower(trim($data['name']));
        if ($this->repo->query()->where('slug', $slug)->exists()) {
            throw new DomainException('An employee with this name already exists (payroll IDs use the name).');
        }

        return $this->repo->create($data + ['slug' => $slug, 'is_active' => true]);
    }

    public function update(Employee $e, array $data): Employee
    {
        if (isset($data['name']) && trim($data['name']) !== $e->name && $e->payrolls()->exists()) {
            throw new DomainException('Renaming would break existing payroll IDs; create a new employee instead.');
        }

        return $this->repo->update($e, $data);
    }
}
