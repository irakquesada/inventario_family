<?php

namespace Tests\Feature;

use App\Livewire\Admin\LandingSettings;
use App\Livewire\Admin\ReviewInvitations;
use App\Livewire\Public\SubmitTestimonial;
use App\Models\ReviewInvitation;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\NewTestimonialNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicTestimonialTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_a_limited_qr_invitation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ReviewInvitations::class)
            ->set('label', 'Mesa 4')
            ->set('maxSubmissions', '2')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertSet('generatedUrl', fn ($url) => str_contains($url, '/opinar/'))
            ->assertSet('qrDataUri', fn ($uri) => str_starts_with($uri, 'data:image/svg+xml'))
            ->assertSee('review-qr-print', false)
            ->assertSee('Imprimir solo el QR');

        $this->assertDatabaseHas('review_invitations', ['label' => 'Mesa 4', 'max_submissions' => 2]);
        $this->assertNotNull(ReviewInvitation::first()->token_hash);
    }

    public function test_revoked_invitation_shows_a_friendly_public_page_instead_of_a_404(): void
    {
        $token = str_repeat('r', 64);
        ReviewInvitation::query()->create([
            'token_hash' => hash('sha256', $token), 'max_submissions' => 1,
            'expires_at' => now()->addDay(), 'revoked_at' => now(),
        ]);

        $this->get(route('public.testimonials.submit', ['token' => $token]))
            ->assertOk()
            ->assertSee('Este QR ya no está disponible')
            ->assertSee('Volver a '.config('brand.name'));
    }

    public function test_unknown_invitation_also_uses_the_safe_public_state(): void
    {
        $this->get(route('public.testimonials.submit', ['token' => str_repeat('x', 64)]))
            ->assertOk()
            ->assertSee('solicita otro QR');
    }

    public function test_customer_submission_is_private_pending_and_notifies_admin(): void
    {
        Storage::fake('public');
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $token = str_repeat('a', 64);
        $invitation = ReviewInvitation::query()->create([
            'token_hash' => hash('sha256', $token), 'created_by' => $admin->id,
            'max_submissions' => 1, 'expires_at' => now()->addDay(),
        ]);

        Livewire::test(SubmitTestimonial::class, ['token' => $token])
            ->set('name', 'Laura Pérez')->set('email', 'Laura@Example.com')->set('rating', 5)
            ->set('comment', 'Una experiencia excelente y un trato maravilloso.')
            ->set('photo', UploadedFile::fake()->image('laura.jpg'))
            ->set('privacyAccepted', true)->set('marketingAccepted', false)
            ->call('submit')->assertHasNoErrors()->assertSet('submitted', true);

        $testimonial = Testimonial::query()->firstOrFail();
        $this->assertSame('pending', $testimonial->moderation_status);
        $this->assertFalse($testimonial->is_active);
        $this->assertSame('laura@example.com', $testimonial->customer_email);
        $this->assertNull($testimonial->marketing_consented_at);
        $this->assertSame(1, $invitation->fresh()->submissions_count);
        Storage::disk('public')->assertExists($testimonial->profile_photo_path);
        Notification::assertSentTo($admin, NewTestimonialNotification::class);
    }

    public function test_admin_can_approve_a_pending_review_for_the_public_landing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $testimonial = Testimonial::query()->create([
            'customer_name' => 'Cliente real', 'customer_email' => 'cliente@example.com',
            'rating' => 5, 'comment' => 'Todo estuvo realmente fenomenal.',
            'source' => 'qr', 'moderation_status' => 'pending', 'is_active' => false,
        ]);

        Livewire::actingAs($admin)->test(LandingSettings::class)
            ->call('approveTestimonial', $testimonial->id)->assertHasNoErrors();

        $this->assertDatabaseHas('testimonials', [
            'id' => $testimonial->id, 'moderation_status' => 'published', 'is_active' => true,
        ]);
        $this->get(route('home'))->assertOk()->assertSee('Cliente real')->assertSee('Todo estuvo realmente fenomenal.');
    }
}
