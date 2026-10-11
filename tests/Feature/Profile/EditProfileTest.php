<?php

namespace Tests\Feature\Profile;

use App\Livewire\Profile\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EditProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_personalize_profile_and_upload_an_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(EditProfile::class)
            ->set('name', 'Irak Quesada')
            ->set('phone', '600 123 123')
            ->set('birthday', '1990-05-14')
            ->set('city', 'Valencia')
            ->set('bio', 'Me gusta cocinar y organizarlo todo.')
            ->set('avatar', UploadedFile::fake()->image('perfil.jpg', 500, 500))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Tu perfil se ha actualizado.');

        $user->refresh();
        $this->assertSame('Irak Quesada', $user->name);
        $this->assertSame('Valencia', $user->city);
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }
}
