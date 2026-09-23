<?php

namespace App\Livewire\Profile;

use App\Support\NotificationPreferences;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class NotificationSettings extends Component
{
    public array $preferences = [];

    public string $telegramChatId = '';

    public function mount(): void
    {
        $setting = auth()->user()->notificationPreference;
        $this->preferences = array_replace_recursive(NotificationPreferences::defaults(), $setting?->preferences ?? []);
        $this->telegramChatId = (string) ($setting?->telegram_chat_id ?? '');
    }

    public function save(): void
    {
        $rules = ['telegramChatId' => ['nullable', 'string', 'max:100']];
        foreach (array_keys(NotificationPreferences::EVENTS) as $event) {
            foreach (['database', 'mail', 'telegram'] as $channel) {
                $rules["preferences.{$event}.{$channel}"] = ['required', 'boolean'];
            }
        }
        $data = $this->validate($rules);
        $telegramSelected = collect($data['preferences'])->contains(fn (array $channels) => $channels['telegram']);
        if ($telegramSelected && trim($data['telegramChatId']) === '') {
            throw ValidationException::withMessages(['telegramChatId' => 'Indica tu Chat ID para activar Telegram.']);
        }

        auth()->user()->notificationPreference()->updateOrCreate([], [
            'telegram_chat_id' => trim($data['telegramChatId']) ?: null,
            'preferences' => $data['preferences'],
        ]);
        session()->flash('status', 'Preferencias de notificación actualizadas.');
    }

    public function render()
    {
        return view('livewire.profile.notification-settings', [
            'events' => NotificationPreferences::EVENTS,
            'telegramAvailable' => (bool) config('services.telegram.enabled'),
        ])->layout('layouts.app', ['title' => 'Mis notificaciones']);
    }
}
