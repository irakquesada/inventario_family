<?php

namespace App\Livewire\People;

use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeesIndex extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $employeeNumber = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $taxId = '';

    public string $socialSecurityNumber = '';

    public string $postalAddress = '';

    public string $iban = '';

    public string $position = '';

    public string $positionId = '';

    public string $hiredOn = '';

    public string $userId = '';

    public string $notes = '';

    public bool $isActive = true;

    public string $search = '';

    public function updatedUserId(string $userId): void
    {
        if ($userId === '') {
            return;
        }
        $user = User::query()->findOrFail($userId);
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('people.manage'), 403);
        $data = $this->validate([
            'employeeNumber' => ['required', 'string', 'max:40', Rule::unique('employees', 'employee_number')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'], 'positionId' => ['nullable', 'integer', 'exists:job_positions,id'],
            'taxId' => ['nullable', 'string', 'max:40'], 'socialSecurityNumber' => ['nullable', 'string', 'max:50'],
            'postalAddress' => ['nullable', 'string', 'max:500'], 'iban' => ['nullable', 'string', 'max:50'],
            'hiredOn' => ['nullable', 'date'], 'userId' => ['nullable', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($this->editingId)],
            'notes' => ['nullable', 'string', 'max:2000'], 'isActive' => ['boolean'],
        ]);
        Employee::query()->updateOrCreate(['id' => $this->editingId], [
            'employee_number' => str($data['employeeNumber'])->upper(), 'name' => $data['name'],
            'email' => $data['email'] ?: null, 'phone' => $data['phone'] ?: null, 'job_position_id' => $data['positionId'] ?: null,
            'tax_id' => $data['taxId'] ?: null, 'social_security_number' => $data['socialSecurityNumber'] ?: null,
            'postal_address' => $data['postalAddress'] ?: null, 'iban' => $data['iban'] ? str($data['iban'])->replace(' ', '')->upper() : null,
            'hired_on' => $data['hiredOn'] ?: null, 'user_id' => $data['userId'] ?: null,
            'notes' => $data['notes'] ?: null, 'is_active' => $data['isActive'],
        ]);
        $this->resetForm();
        session()->flash('status', 'Empleado guardado correctamente.');
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()?->can('people.manage'), 403);
        $employee = Employee::query()->findOrFail($id);
        $this->editingId = $employee->id;
        $this->employeeNumber = $employee->employee_number;
        $this->name = $employee->name;
        $this->email = $employee->email ?? '';
        $this->phone = $employee->phone ?? '';
        $this->taxId = $employee->tax_id ?? '';
        $this->socialSecurityNumber = $employee->social_security_number ?? '';
        $this->postalAddress = $employee->postal_address ?? '';
        $this->iban = $employee->iban ?? '';
        $this->position = $employee->position ?? '';
        $this->positionId = (string) ($employee->job_position_id ?? '');
        $this->hiredOn = $employee->hired_on?->toDateString() ?? '';
        $this->userId = (string) ($employee->user_id ?? '');
        $this->notes = $employee->notes ?? '';
        $this->isActive = $employee->is_active;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'employeeNumber', 'name', 'email', 'phone', 'taxId', 'socialSecurityNumber', 'postalAddress', 'iban', 'position', 'positionId', 'hiredOn', 'userId', 'notes']);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.people.employees-index', [
            'employees' => Employee::query()->with(['user', 'jobPosition'])->when($this->search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('employee_number', 'like', '%'.$this->search.'%')->orWhereHas('jobPosition', fn ($position) => $position->where('name', 'like', '%'.$this->search.'%'))))->orderBy('name')->paginate(15),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'positions' => JobPosition::query()->where('is_active', true)->orWhere('id', $this->positionId ?: 0)->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Personal']);
    }
}
