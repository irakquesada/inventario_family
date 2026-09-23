<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityLogsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $subject = '';

    public ?int $expandedId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function updatedSubject(): void
    {
        $this->resetPage();
    }

    public function toggleDetails(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function render()
    {
        return view('livewire.admin.activity-logs-index', [
            'logs' => ActivityLog::query()->with('user')
                ->when($this->action, fn ($q) => $q->where('action', $this->action))
                ->when($this->subject, fn ($q) => $q->where('subject_type', $this->subject))
                ->when($this->search, fn ($q) => $q->where(function ($q): void {
                    $q->where('subject_label', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%'));
                }))->latest('occurred_at')->paginate(30),
            'subjectTypes' => ActivityLog::query()->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ])->layout('layouts.app', ['title' => 'Auditoría de operaciones']);
    }
}
