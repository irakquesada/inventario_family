<?php

namespace App\Livewire\Admin;

use App\Support\ModuleManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PlatformModules extends Component
{
    public string $password = '';

    public array $modules = [];

    public function mount(ModuleManager $manager): void
    {
        $this->modules = collect($manager->all())->mapWithKeys(fn (array $module, string $key) => [$key => $module['enabled']])->all();
    }

    public function unlock(): void
    {
        $key = 'platform-modules:'.auth()->id().':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['password' => 'Demasiados intentos. Espera unos minutos.']);
        }
        $this->validate(['password' => ['required', 'string']]);
        if (! Hash::check($this->password, auth()->user()->password)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }

        RateLimiter::clear($key);
        session()->put('platform_modules_unlocked_until', now()->addSeconds(config('platform.module_unlock_seconds'))->timestamp);
        $this->reset('password');
    }

    public function save(ModuleManager $manager): void
    {
        abort_unless($this->isUnlocked(), 403);
        $rules = [];
        foreach (array_keys(ModuleManager::MODULES) as $key) {
            $rules["modules.{$key}"] = ['required', 'boolean'];
        }
        $data = $this->validate($rules);
        $manager->update($data['modules'], auth()->user());
        session()->flash('status', 'Configuración modular actualizada. Los datos de los módulos desactivados se conservan.');
    }

    public function lock(): void
    {
        session()->forget('platform_modules_unlocked_until');
    }

    public function render()
    {
        return view('livewire.admin.platform-modules', [
            'definitions' => ModuleManager::MODULES,
            'unlocked' => $this->isUnlocked(),
        ])->layout('layouts.app', ['title' => 'Configuración de la plataforma']);
    }

    private function isUnlocked(): bool
    {
        return (int) session('platform_modules_unlocked_until', 0) > now()->timestamp;
    }
}
