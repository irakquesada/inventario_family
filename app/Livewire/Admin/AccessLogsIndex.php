<?php

namespace App\Livewire\Admin;

use App\Models\AccessLog;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AccessLogsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $event = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $logs = AccessLog::query()
            ->with('user')
            ->when($this->event, fn ($query) => $query->where('event', $this->event))
            ->when($this->search, fn ($query) => $query->whereHas('user', function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->latest('occurred_at')
            ->paginate(30);

        return view('livewire.admin.access-logs-index', compact('logs'))
            ->layout('layouts.app', ['title' => 'Registro de accesos']);
    }
}
