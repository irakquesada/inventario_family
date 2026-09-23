<?php

namespace App\Livewire\People;

use App\Models\AbsenceType;
use App\Models\Employee;
use App\Services\People\CreateAbsenceRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class MyAbsences extends Component
{
    use WithFileUploads;

    public string $typeId = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public bool $isFullDay = true;

    public string $startsAt = '';

    public string $endsAt = '';

    public string $reason = '';

    public $attachment;

    public function mount(): void
    {
        $this->startsOn = today()->toDateString();
        $this->endsOn = today()->toDateString();
    }

    public function submit(CreateAbsenceRequest $creator): void
    {
        $employee = $this->employee();
        $data = $this->validate([
            'typeId' => ['required', 'integer', Rule::exists('absence_types', 'id')->where('is_active', true)],
            'startsOn' => ['required', 'date', 'after_or_equal:today'], 'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
            'isFullDay' => ['boolean'], 'startsAt' => [Rule::requiredIf(! $this->isFullDay), 'nullable', 'date_format:H:i'],
            'endsAt' => [Rule::requiredIf(! $this->isFullDay), 'nullable', 'date_format:H:i'], 'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $type = AbsenceType::query()->findOrFail($data['typeId']);
        if ($type->requires_attachment && ! $this->attachment) {
            $this->addError('attachment', 'Este tipo de ausencia requiere un justificante.');

            return;
        }
        $path = $this->attachment?->store('absence-attachments/'.$employee->id, 'local');
        try {
            $creator->handle($employee, $type, [
                'starts_on' => $data['startsOn'], 'ends_on' => $data['endsOn'], 'is_full_day' => $data['isFullDay'],
                'starts_at' => $data['startsAt'] ?: null, 'ends_at' => $data['endsAt'] ?: null, 'reason' => $data['reason'],
                'attachment_path' => $path, 'attachment_name' => $this->attachment?->getClientOriginalName(),
            ]);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $exception;
        }
        $this->reset(['typeId', 'startsAt', 'endsAt', 'reason', 'attachment']);
        $this->isFullDay = true;
        $this->startsOn = today()->toDateString();
        $this->endsOn = today()->toDateString();
        session()->flash('status', 'Solicitud enviada al administrador.');
    }

    public function cancel(int $id): void
    {
        $request = $this->employee()->absenceRequests()->findOrFail($id);
        if ($request->status !== 'pending') {
            $this->addError('cancel', 'Solo puedes cancelar solicitudes pendientes.');

            return;
        }
        $request->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    public function render()
    {
        $employee = $this->employee();
        $year = now()->year;
        $entitled = (float) ($employee->absenceBalances()->where('year', $year)->value('entitled_days') ?? 0);
        $used = (float) $employee->absenceRequests()->where('status', 'approved')->whereYear('starts_on', $year)->whereHas('type', fn ($q) => $q->where('deducts_balance', true))->sum('requested_days');

        return view('livewire.people.my-absences', [
            'employee' => $employee, 'types' => AbsenceType::query()->where('is_active', true)->orderBy('name')->get(),
            'requests' => $employee->absenceRequests()->with(['type', 'reviewer'])->latest()->get(),
            'entitled' => $entitled, 'used' => $used, 'remaining' => max(0, $entitled - $used),
        ])->layout('layouts.app', ['title' => 'Mis ausencias']);
    }

    private function employee(): Employee
    {
        return Employee::query()->where('user_id', auth()->id())->firstOrFail();
    }
}
