<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\LabelsIndex;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LabelsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_generate_barcode_labels_as_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $product = Product::query()->create([
            'name' => 'Aceite de oliva', 'slug' => 'aceite', 'sku' => 'ACE-001',
            'barcode' => '8412345678901', 'price' => 7.5,
        ]);

        Livewire::actingAs($staff)->test(LabelsIndex::class)
            ->set('selectedProductIds', [$product->id])
            ->set('copies', 2)
            ->set('columns', 3)
            ->call('download')
            ->assertFileDownloaded();
    }

    public function test_a_product_selection_is_required(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        Livewire::actingAs($staff)->test(LabelsIndex::class)
            ->call('download')
            ->assertHasErrors(['selectedProductIds']);
    }

    public function test_user_without_permission_cannot_open_labels(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('inventory.labels.index'))->assertForbidden();
    }
}
