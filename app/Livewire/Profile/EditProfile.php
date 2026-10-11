<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditProfile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $phone = '';

    public string $birthday = '';

    public string $city = '';

    public string $bio = '';

    public string $existingAvatarPath = '';

    public $avatar;

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->phone = (string) $user->phone;
        $this->birthday = $user->birthday?->format('Y-m-d') ?? '';
        $this->city = (string) $user->city;
        $this->bio = (string) $user->bio;
        $this->existingAvatarPath = (string) $user->avatar_path;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'city' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $user = auth()->user();
        $previousAvatar = $user->avatar_path;
        $avatarPath = $this->avatar?->store('avatars', 'public') ?: $previousAvatar;
        $user->update([
            'name' => trim($data['name']),
            'phone' => $data['phone'] ?: null,
            'birthday' => $data['birthday'] ?: null,
            'city' => $data['city'] ?: null,
            'bio' => $data['bio'] ?: null,
            'avatar_path' => $avatarPath,
        ]);
        if ($this->avatar && $previousAvatar && $previousAvatar !== $avatarPath) {
            Storage::disk('public')->delete($previousAvatar);
        }
        $this->existingAvatarPath = (string) $avatarPath;
        $this->avatar = null;
        session()->flash('status', 'Tu perfil se ha actualizado.');
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->update(['avatar_path' => null]);
        $this->reset(['avatar', 'existingAvatarPath']);
        session()->flash('status', 'Foto de perfil eliminada.');
    }

    public function render()
    {
        return view('livewire.profile.edit-profile')->layout('layouts.app', ['title' => 'Mi perfil']);
    }
}
