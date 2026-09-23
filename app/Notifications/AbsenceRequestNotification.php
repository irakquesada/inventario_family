<?php

namespace App\Notifications;

use App\Models\AbsenceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AbsenceRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public AbsenceRequest $request, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = match ($this->event) {
            'submitted' => "{$this->request->employee->name} ha solicitado {$this->request->type->name}.",
            'approved' => "Tu solicitud de {$this->request->type->name} ha sido aprobada.",
            'rejected' => "Tu solicitud de {$this->request->type->name} ha sido rechazada.",
            default => "Se ha actualizado una solicitud de {$this->request->type->name}.",
        };

        return ['kind' => 'absence_'.$this->event, 'absence_request_id' => $this->request->id, 'title' => 'Ausencias', 'message' => $message];
    }
}
