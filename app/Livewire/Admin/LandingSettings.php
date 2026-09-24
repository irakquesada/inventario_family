<?php

namespace App\Livewire\Admin;

use App\Events\LandingUpdated;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class LandingSettings extends Component
{
    public string $heroTitle = '';

    public string $heroSubtitle = '';

    public string $heroCta = 'Reservar';

    public string $aboutTitle = 'Conócenos';

    public string $aboutText = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $mapEmbedUrl = '';

    public string $instagramUrl = '';

    public string $facebookUrl = '';

    public bool $reservationsEnabled = true;

    public bool $testimonialsEnabled = true;

    public bool $carouselEnabled = true;

    public bool $jobsEnabled = false;

    public string $jobsEmail = '';

    public string $privacyPolicy = '';

    public string $legalNotice = '';

    public array $featuredProducts = [];

    public ?int $editingTestimonialId = null;

    public string $testimonialName = '';

    public string $testimonialRating = '5';

    public string $testimonialComment = '';

    public string $testimonialOrder = '0';

    public bool $testimonialActive = true;

    public function mount(): void
    {
        $settings = SiteSetting::query()->firstOrCreate([], ['hero_title' => config('brand.name')]);
        foreach (['heroTitle' => 'hero_title', 'heroSubtitle' => 'hero_subtitle', 'heroCta' => 'hero_cta', 'aboutTitle' => 'about_title', 'aboutText' => 'about_text', 'phone' => 'phone', 'email' => 'email', 'address' => 'address', 'mapEmbedUrl' => 'map_embed_url', 'instagramUrl' => 'instagram_url', 'facebookUrl' => 'facebook_url', 'jobsEmail' => 'jobs_email', 'privacyPolicy' => 'privacy_policy', 'legalNotice' => 'legal_notice'] as $property => $column) {
            $this->{$property} = (string) ($settings->{$column} ?? '');
        }
        $this->reservationsEnabled = $settings->reservations_enabled;
        $this->testimonialsEnabled = $settings->testimonials_enabled;
        $this->carouselEnabled = $settings->carousel_enabled;
        $this->jobsEnabled = $settings->jobs_enabled;
        $this->featuredProducts = Product::query()->where('featured_on_landing', true)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function save(): void
    {
        $this->authorizeContent();
        $data = $this->validate([
            'heroTitle' => ['required', 'string', 'max:255'], 'heroSubtitle' => ['nullable', 'string', 'max:1000'],
            'heroCta' => ['required', 'string', 'max:60'], 'aboutTitle' => ['required', 'string', 'max:255'],
            'aboutText' => ['nullable', 'string', 'max:5000'], 'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:1000'],
            'mapEmbedUrl' => ['nullable', 'url:https', 'max:2000'], 'instagramUrl' => ['nullable', 'url:https', 'max:1000'],
            'facebookUrl' => ['nullable', 'url:https', 'max:1000'], 'jobsEmail' => ['nullable', 'email', 'max:255'],
            'reservationsEnabled' => ['boolean'], 'testimonialsEnabled' => ['boolean'], 'jobsEnabled' => ['boolean'],
            'carouselEnabled' => ['boolean'],
            'privacyPolicy' => ['nullable', 'string', 'max:50000'], 'legalNotice' => ['nullable', 'string', 'max:50000'],
            'featuredProducts' => ['array', 'max:8'], 'featuredProducts.*' => ['integer', 'exists:products,id'],
        ]);
        DB::transaction(function () use ($data): void {
            SiteSetting::query()->firstOrFail()->update([
                'hero_title' => $data['heroTitle'], 'hero_subtitle' => $data['heroSubtitle'] ?: null, 'hero_cta' => $data['heroCta'],
                'about_title' => $data['aboutTitle'], 'about_text' => $data['aboutText'] ?: null,
                'phone' => $data['phone'] ?: null, 'email' => $data['email'] ?: null, 'address' => $data['address'] ?: null,
                'map_embed_url' => $data['mapEmbedUrl'] ?: null, 'instagram_url' => $data['instagramUrl'] ?: null,
                'facebook_url' => $data['facebookUrl'] ?: null, 'reservations_enabled' => $data['reservationsEnabled'],
                'testimonials_enabled' => $data['testimonialsEnabled'], 'jobs_enabled' => $data['jobsEnabled'],
                'carousel_enabled' => $data['carouselEnabled'],
                'jobs_email' => $data['jobsEmail'] ?: null,
                'privacy_policy' => $data['privacyPolicy'] ?: null, 'legal_notice' => $data['legalNotice'] ?: null,
            ]);
            Product::query()->update(['featured_on_landing' => false]);
            Product::query()->whereKey($data['featuredProducts'])->update(['featured_on_landing' => true]);
        });
        LandingUpdated::dispatch();
        session()->flash('status', 'Landing actualizada correctamente.');
    }

    public function saveTestimonial(): void
    {
        $this->authorizeContent();
        $data = $this->validate([
            'testimonialName' => ['required', 'string', 'max:255'], 'testimonialRating' => ['required', 'integer', 'min:1', 'max:5'],
            'testimonialComment' => ['required', 'string', 'max:1500'], 'testimonialOrder' => ['required', 'integer', 'min:0', 'max:1000'],
            'testimonialActive' => ['boolean'],
        ]);
        $testimonial = $this->editingTestimonialId
            ? Testimonial::query()->findOrFail($this->editingTestimonialId)
            : new Testimonial(['source' => 'manual']);
        $testimonial->fill([
            'customer_name' => $data['testimonialName'], 'rating' => $data['testimonialRating'],
            'comment' => $data['testimonialComment'], 'sort_order' => $data['testimonialOrder'], 'is_active' => $data['testimonialActive'],
            'moderation_status' => $data['testimonialActive'] ? 'published' : ($testimonial->moderation_status ?: 'pending'),
        ])->save();
        LandingUpdated::dispatch();
        $this->resetTestimonial();
        session()->flash('status', 'Testimonio guardado.');
    }

    public function editTestimonial(int $id): void
    {
        $this->authorizeContent();
        $item = Testimonial::query()->findOrFail($id);
        $this->editingTestimonialId = $item->id;
        $this->testimonialName = $item->customer_name;
        $this->testimonialRating = (string) $item->rating;
        $this->testimonialComment = $item->comment;
        $this->testimonialOrder = (string) $item->sort_order;
        $this->testimonialActive = $item->is_active;
    }

    public function deleteTestimonial(int $id): void
    {
        $this->authorizeContent();
        $testimonial = Testimonial::query()->findOrFail($id);
        if ($testimonial->profile_photo_path) {
            Storage::disk('public')->delete($testimonial->profile_photo_path);
        }
        $testimonial->delete();
        LandingUpdated::dispatch();
        $this->resetTestimonial();
    }

    public function approveTestimonial(int $id): void
    {
        $this->authorizeContent();
        Testimonial::query()->findOrFail($id)->update([
            'moderation_status' => 'published',
            'is_active' => true,
        ]);
        LandingUpdated::dispatch();
        session()->flash('status', 'Reseña aprobada y publicada.');
    }

    public function rejectTestimonial(int $id): void
    {
        $this->authorizeContent();
        Testimonial::query()->findOrFail($id)->update([
            'moderation_status' => 'rejected',
            'is_active' => false,
        ]);
        LandingUpdated::dispatch();
        session()->flash('status', 'Reseña rechazada y ocultada.');
    }

    public function resetTestimonial(): void
    {
        $this->reset(['editingTestimonialId', 'testimonialName', 'testimonialComment']);
        $this->testimonialRating = '5';
        $this->testimonialOrder = '0';
        $this->testimonialActive = true;
    }

    public function render()
    {
        return view('livewire.admin.landing-settings', [
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'testimonials' => Testimonial::query()
                ->orderByRaw("CASE moderation_status WHEN 'pending' THEN 0 WHEN 'published' THEN 1 ELSE 2 END")
                ->orderBy('sort_order')->latest('id')->get(),
        ])->layout('layouts.app', ['title' => 'Landing pública']);
    }

    private function authorizeContent(): void
    {
        abort_unless(auth()->user()?->can('content.manage'), 403);
    }
}
