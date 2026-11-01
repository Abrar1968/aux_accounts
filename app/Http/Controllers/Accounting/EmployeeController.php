<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreEmployeeRequest;
use App\Http\Requests\Accounting\UpdateEmployeeRequest;
use App\Http\Resources\RecordResource;
use App\Models\Employee;
use App\Services\Accounting\AdvanceService;
use App\Services\Accounting\EmployeeService;
use App\Services\Shared\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees)
    {
    }

    public function index(Request $request, ListingService $list, AdvanceService $advances): Response
    {
        return Inertia::render('Accounting/Employees/Index', [
            'employees' => RecordResource::collection($list->page('employees', $request->query() + ['per_page' => 100])),
            'advances_outstanding' => $advances->outstanding(),
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $e = $this->employees->create($request->validated());

        return $this->toast("{$e->name} added.");
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $this->employees->update($employee, $request->validated());

        return $this->toast("{$employee->name} updated.");
    }
}
