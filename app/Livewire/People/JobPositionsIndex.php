<?php

namespace App\Livewire\People;

use App\Models\JobPosition;
use Illuminate\Validation\Rule;
use Livewire\Component;

class JobPositionsIndex extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public bool $isActive = true;

    public function save(): void
    {
        abort_unless(auth()->user()?->can('people.manage'), 403);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('job_positions', 'name')->ignore($this->editingId)],
            'code' => ['required', 'string', 'max:40', Rule::unique('job_positions', 'code')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:2000'], 'isActive' => ['boolean'],
        ]);
        JobPosition::query()->updateOrCreate(['id' => $this->editingId], [
            'name' => $data['name'], 'code' => str($data['code'])->upper(),
            'description' => $data['description'] ?: null, 'is_active' => $data['isActive'],
        ]);
        $this->resetForm();
        session()->flash('status', 'Puesto guardado correctamente.');
    }

    public function edit(int $id): void
    {
        $position = JobPosition::query()->findOrFail($id);
        $this->editingId = $position->id;
        $this->name = $position->name;
        $this->code = $position->code;
        $this->description = $position->description ?? '';
        $this->isActive = $position->is_active;
    }

    public function delete(int $id): void
    {
        $position = JobPosition::query()->withCount('employees')->findOrFail($id);
        if ($position->employees_count > 0) {
            $this->addError('delete', 'No se puede eliminar un puesto asignado. Puedes desactivarlo.');

            return;
        }
        $position->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'description']);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.people.job-positions-index', [
            'positions' => JobPosition::query()->withCount('employees')->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Puestos']);
    }
}
