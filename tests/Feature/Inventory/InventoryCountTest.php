<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\CountShow;
use App\Models\InventoryBalance;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\CompleteInventoryCount;
use App\Services\Inventory\CreateInventoryCount;
use App\Services\Inventory\LotStock;
use App\Services\Inventory\RecordStockMovement;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class InventoryCountTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->staff = User::factory()->create();
        $this->staff->assignRole('staff');
        $this->warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'MAIN']);
    }

    public function test_opening_a_count_snapshots_all_active_products_and_prevents_another_open_count(): void
    {
        $active = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        Product::query()->create(['name' => 'Oculto', 'slug' => 'oculto', 'sku' => 'OCU-001', 'is_active' => false]);
        InventoryBalance::query()->create(['product_id' => $active->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 12]);

        $count = app(CreateInventoryCount::class)->handle($this->warehouse, $this->admin, 'Conteo mensual');

        $this->assertSame(1, $count->items()->count());
        $this->assertDatabaseHas('inventory_count_items', [
            'inventory_count_id' => $count->id, 'product_id' => $active->id, 'expected_quantity' => 12,
        ]);

        $this->expectException(ValidationException::class);
        app(CreateInventoryCount::class)->handle($this->warehouse, $this->admin);
    }

    public function test_staff_can_capture_physical_quantity_with_identity_and_timestamp(): void
    {
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        $count = app(CreateInventoryCount::class)->handle($this->warehouse, $this->admin);
        $item = $count->items()->whereBelongsTo($product)->firstOrFail();

        Livewire::actingAs($this->staff)->test(CountShow::class, ['inventoryCount' => $count])
            ->set("quantities.{$item->id}", '9.5')
            ->call('saveItem', $item->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('inventory_count_items', [
            'id' => $item->id, 'counted_quantity' => 9.5, 'counted_by' => $this->staff->id,
        ]);
        $this->assertNotNull($item->fresh()->counted_at);
    }

    public function test_completion_reconciles_against_latest_balance_and_records_auditable_adjustment(): void
    {
        $product = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        app(RecordStockMovement::class)->handle($product, $this->warehouse, 'receipt', 10, $this->staff);
        $count = app(CreateInventoryCount::class)->handle($this->warehouse, $this->admin);
        $count->items()->firstOrFail()->update(['counted_quantity' => 8, 'counted_by' => $this->staff->id, 'counted_at' => now()]);

        app(RecordStockMovement::class)->handle($product, $this->warehouse, 'receipt', 2, $this->staff, 'Entrada durante el conteo');
        app(CompleteInventoryCount::class)->handle($count, $this->admin);

        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 8,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'user_id' => $this->admin->id,
            'type' => 'stocktake_adjustment', 'quantity' => -4, 'operation_reference' => $count->reference,
        ]);
        $this->assertSame('completed', $count->fresh()->status);
        $this->assertSame($this->admin->id, $count->fresh()->completed_by);
    }

    public function test_incomplete_count_cannot_be_finalized(): void
    {
        Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        $count = app(CreateInventoryCount::class)->handle($this->warehouse, $this->admin);

        try {
            app(CompleteInventoryCount::class)->handle($count, $this->admin);
            $this->fail('El conteo incompleto debía rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Faltan 1', $exception->errors()['count'][0]);
        }

        $this->assertSame('open', $count->fresh()->status);
        $this->assertDatabaseMissing('stock_movements', ['type' => 'stocktake_adjustment']);
    }

    public function test_reconciliation_rolls_back_every_adjustment_if_one_item_fails(): void
    {
        $first = Product::query()->create(['name' => 'Arroz', 'slug' => 'arroz', 'sku' => 'ARR-001']);
        $second = Product::query()->create(['name' => 'Agua', 'slug' => 'agua', 'sku' => 'AGU-001']);
        app(RecordStockMovement::class)->handle($first, $this->warehouse, 'receipt', 5);
        app(RecordStockMovement::class)->handle($second, $this->warehouse, 'receipt', 5);
        $count = app(CreateInventoryCount::class)->handle($this->warehouse, $this->admin);
        $count->items()->update(['counted_quantity' => 3, 'counted_by' => $this->staff->id, 'counted_at' => now()]);
        $realRecorder = app(RecordStockMovement::class);
        $failingRecorder = new class($realRecorder) extends RecordStockMovement
        {
            private int $calls = 0;

            public function __construct(private RecordStockMovement $real) {}

            public function handle(Product $product, Warehouse $warehouse, string $type, float $quantity, ?User $user = null, ?string $reason = null, array $metadata = [], ?string $operationReference = null): StockMovement
            {
                if (++$this->calls === 2) {
                    throw new RuntimeException('Fallo simulado');
                }

                return $this->real->handle($product, $warehouse, $type, $quantity, $user, $reason, $metadata, $operationReference);
            }
        };

        try {
            (new CompleteInventoryCount($failingRecorder, app(LotStock::class)))->handle($count, $this->admin);
            $this->fail('La conciliación debía fallar.');
        } catch (RuntimeException) {
            // Fallo intencional para comprobar la atomicidad.
        }

        $this->assertSame(0, StockMovement::query()->where('type', 'stocktake_adjustment')->count());
        $this->assertSame(5.0, (float) InventoryBalance::query()->whereBelongsTo($first)->whereBelongsTo($this->warehouse)->value('quantity'));
        $this->assertSame(5.0, (float) InventoryBalance::query()->whereBelongsTo($second)->whereBelongsTo($this->warehouse)->value('quantity'));
        $this->assertSame('open', $count->fresh()->status);
    }

    public function test_staff_cannot_approve_a_count(): void
    {
        $count = InventoryCount::query()->create([
            'reference' => fake()->uuid(), 'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id, 'status' => 'open', 'started_at' => now(),
        ]);

        Livewire::actingAs($this->staff)->test(CountShow::class, ['inventoryCount' => $count])
            ->call('complete')
            ->assertForbidden();
    }
}
