<?php

namespace App\Livewire;

use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $kind = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedKind(): void
    {
        $this->resetPage();
    }

    public function markRead(string $id): void
    {
        $this->notification($id)->markAsRead();
    }

    public function markUnread(string $id): void
    {
        $this->notification($id)->markAsUnread();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        session()->flash('status', 'Todas las notificaciones se han marcado como vistas.');
    }

    public function render()
    {
        $query = auth()->user()->notifications()->getQuery()
            ->when($this->status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($this->status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($this->kind, fn ($query) => $query->where('data->kind', $this->kind))
            ->when($this->search, function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where('data', 'like', $term);
            });

        return view('livewire.notifications-index', [
            'notifications' => $query->latest()->paginate(20),
            'totalCount' => auth()->user()->notifications()->count(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
            'kinds' => [
                'low_stock' => 'Stock bajo',
                'expiring_lot' => 'Caducidad',
                'new_reservation' => 'Reservas',
                'new_testimonial' => 'Reseñas',
                'absence_submitted' => 'Solicitudes de ausencia',
                'absence_approved' => 'Ausencias aprobadas',
                'absence_rejected' => 'Ausencias rechazadas',
            ],
        ])->layout('layouts.app', ['title' => 'Notificaciones']);
    }

    private function notification(string $id): DatabaseNotification
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();
        abort_unless($notification, 404);

        return $notification;
    }
}
