<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\ProductsIndex;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\ProductCsv;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProductCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_import_new_products_and_update_existing_ones_by_sku(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Product::query()->create(['name' => 'Nombre anterior', 'slug' => 'anterior', 'sku' => 'ARR-001']);
        $contents = implode("\n", [
            implode(';', ProductCsv::HEADERS),
            'ARR-001;Arroz bomba;841000000001;Despensa;Chetaco;kg;2,50;4,90;5;si;si;si;Formato profesional',
            'AGU-001;Agua mineral;841000000002;Bebidas;Manantial;unidad;0,40;1,20;10;no;si;si;',
        ]);

        Livewire::actingAs($staff)->test(ProductsIndex::class)
            ->set('importFile', UploadedFile::fake()->createWithContent('productos.csv', $contents))
            ->call('import')
            ->assertHasNoErrors()
            ->assertSee('1 creados y 1 actualizados');

        $this->assertDatabaseHas('products', ['sku' => 'ARR-001', 'name' => 'Arroz bomba', 'minimum_stock' => 5]);
        $this->assertDatabaseHas('products', ['sku' => 'AGU-001', 'barcode' => '841000000002']);
        $this->assertDatabaseHas('categories', ['name' => 'Despensa']);
        $this->assertDatabaseHas('brands', ['name' => 'Manantial']);
    }

    public function test_invalid_file_is_rejected_before_any_product_is_written(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'products-');
        file_put_contents($path, implode("\n", [
            implode(';', ProductCsv::HEADERS),
            'OK-001;Producto válido;;;Marca;unidad;1;2;3;si;no;si;',
            'BAD-001;Producto inválido;;;Marca;metro;1;2;3;si;no;si;',
        ]));

        try {
            app(ProductCsv::class)->import($path);
            $this->fail('El archivo debía rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Línea 3', $exception->errors()['importFile'][0]);
        } finally {
            unlink($path);
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_staff_can_download_an_excel_safe_export_and_template(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Product::query()->create(['name' => '=FORMULA', 'slug' => 'formula', 'sku' => 'FOR-001']);

        $export = $this->actingAs($staff)->get(route('inventory.products.export'));
        $export->assertOk()->assertDownload();
        $this->assertStringContainsString("'=FORMULA", $export->streamedContent());

        $this->actingAs($staff)->get(route('inventory.products.template'))
            ->assertOk()
            ->assertDownload('plantilla-productos.csv');
    }

    public function test_staff_can_download_valid_excel_and_pdf_catalogs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Product::query()->create([
            'name' => 'Aceite de oliva', 'slug' => 'aceite-de-oliva', 'sku' => 'ACE-001',
            'barcode' => '8412345678901', 'cost' => 4.25, 'price' => 7.5,
        ]);

        $excel = $this->actingAs($staff)->get(route('inventory.products.excel'));
        $excel->assertOk()->assertDownload();
        $excelPath = tempnam(sys_get_temp_dir(), 'products-xlsx-');
        file_put_contents($excelPath, $excel->streamedContent());
        $sheet = IOFactory::load($excelPath)->getActiveSheet();
        $this->assertSame('SKU', $sheet->getCell('A1')->getValue());
        $this->assertSame('ACE-001', $sheet->getCell('A2')->getValue());
        $this->assertSame('8412345678901', $sheet->getCell('C2')->getValue());
        $this->assertSame(7.5, $sheet->getCell('I2')->getValue());
        unlink($excelPath);

        $pdf = $this->actingAs($staff)->get(route('inventory.products.pdf'));
        $pdf->assertOk()->assertDownload();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
    }
}
