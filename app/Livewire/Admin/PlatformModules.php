<?php

namespace App\Livewire\Admin;

use App\Models\SiteSetting;
use App\Support\ModuleManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PlatformModules extends Component
{
    public string $password = '';

    public array $modules = [];

    public string $seoSiteName = '';

    public string $seoTitle = '';

    public string $seoDescription = '';

    public string $seoKeywords = '';

    public string $seoCanonicalUrl = '';

    public string $seoOgImage = '';

    public string $seoBusinessType = 'LocalBusiness';

    public bool $seoIndexingEnabled = true;

    public string $legalBusinessName = '';

    public string $businessTaxId = '';

    public string $socialSecurityAccount = '';

    public string $payrollAddress = '';

    public function mount(ModuleManager $manager): void
    {
        $this->modules = collect($manager->all())->mapWithKeys(fn (array $module, string $key) => [$key => $module['enabled']])->all();
        $settings = SiteSetting::query()->firstOrCreate([], ['hero_title' => config('brand.name')]);
        $this->seoSiteName = $settings->seo_site_name ?: 'Inventario Pro';
        $this->seoTitle = $settings->seo_title ?: 'Inventario Pro | Gestión, productos y reservas';
        $this->seoDescription = $settings->seo_description ?: 'Inventario Pro centraliza productos, disponibilidad, reservas y atención al cliente en una experiencia rápida y clara.';
        $this->seoKeywords = $settings->seo_keywords ?? '';
        $this->seoCanonicalUrl = $settings->seo_canonical_url ?? '';
        $this->seoOgImage = $settings->seo_og_image ?? '';
        $this->seoBusinessType = $settings->seo_business_type ?: 'LocalBusiness';
        $this->seoIndexingEnabled = $settings->seo_indexing_enabled;
        $this->legalBusinessName = $settings->legal_business_name ?? '';
        $this->businessTaxId = $settings->business_tax_id ?? '';
        $this->socialSecurityAccount = $settings->social_security_account ?? '';
        $this->payrollAddress = $settings->payroll_address ?? '';
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

    public function saveSeo(): void
    {
        abort_unless($this->isUnlocked(), 403);
        $data = $this->validate([
            'seoSiteName' => ['required', 'string', 'max:80'], 'seoTitle' => ['required', 'string', 'max:70'],
            'seoDescription' => ['required', 'string', 'max:180'], 'seoKeywords' => ['nullable', 'string', 'max:255'],
            'seoCanonicalUrl' => ['nullable', 'url:http,https', 'max:255'], 'seoOgImage' => ['nullable', 'url:http,https', 'max:500'],
            'seoBusinessType' => ['required', 'in:LocalBusiness,Restaurant,Store,Organization'], 'seoIndexingEnabled' => ['boolean'],
            'legalBusinessName' => ['nullable', 'string', 'max:255'], 'businessTaxId' => ['nullable', 'string', 'max:50'],
            'socialSecurityAccount' => ['nullable', 'string', 'max:50'], 'payrollAddress' => ['nullable', 'string', 'max:500'],
        ]);
        SiteSetting::query()->firstOrCreate([], ['hero_title' => config('brand.name')])->update([
            'seo_site_name' => $data['seoSiteName'], 'seo_title' => $data['seoTitle'], 'seo_description' => $data['seoDescription'],
            'seo_keywords' => $data['seoKeywords'] ?: null, 'seo_canonical_url' => $data['seoCanonicalUrl'] ?: null,
            'seo_og_image' => $data['seoOgImage'] ?: null, 'seo_business_type' => $data['seoBusinessType'],
            'seo_indexing_enabled' => $data['seoIndexingEnabled'],
            'legal_business_name' => $data['legalBusinessName'] ?: null, 'business_tax_id' => $data['businessTaxId'] ?: null,
            'social_security_account' => $data['socialSecurityAccount'] ?: null, 'payroll_address' => $data['payrollAddress'] ?: null,
        ]);
        session()->flash('seo-status', 'Configuración SEO actualizada.');
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
