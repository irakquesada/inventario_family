<?php

namespace App\Services\Inventory;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCsv
{
    public const HEADERS = [
        'sku', 'nombre', 'codigo_barras', 'categoria', 'marca', 'unidad', 'coste',
        'precio', 'stock_minimo', 'avisar_stock_bajo', 'disponible_venta', 'activo', 'descripcion',
    ];

    /** @return array{created: int, updated: int} */
    public function import(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['importFile' => 'No se pudo leer el archivo.']);
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw ValidationException::withMessages(['importFile' => 'El archivo está vacío.']);
            }

            $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);
            $headers = fgetcsv($handle, separator: $delimiter, escape: '');
            $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers ?: []);

            if ($headers !== self::HEADERS) {
                throw ValidationException::withMessages([
                    'importFile' => 'Las columnas no coinciden con la plantilla. Descarga una plantilla nueva.',
                ]);
            }

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle, separator: $delimiter, escape: '')) !== false) {
                $line++;
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($values) !== count(self::HEADERS)) {
                    $this->fail($line, 'cantidad de columnas incorrecta');
                }
                if (count($rows) >= 2000) {
                    throw ValidationException::withMessages(['importFile' => 'El archivo supera el límite de 2.000 productos.']);
                }
                $rows[] = $this->validateRow(array_combine(self::HEADERS, $values), $line);
            }
        } finally {
            fclose($handle);
        }

        $this->validateUniqueValues($rows);

        return DB::transaction(function () use ($rows): array {
            $result = ['created' => 0, 'updated' => 0];

            foreach ($rows as $row) {
                $product = Product::query()->where('sku', $row['sku'])->first();
                $categoryId = $this->categoryId($row['categoria']);
                $brandId = $this->brandId($row['marca']);
                $attributes = [
                    'name' => $row['nombre'],
                    'slug' => Str::slug($row['nombre']).'-'.Str::lower(Str::random(8)),
                    'barcode' => $row['codigo_barras'] ?: null,
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'unit' => $row['unidad'],
                    'cost' => $row['coste'],
                    'price' => $row['precio'],
                    'minimum_stock' => $row['stock_minimo'],
                    'notify_low_stock' => $row['avisar_stock_bajo'],
                    'is_sellable' => $row['disponible_venta'],
                    'is_active' => $row['activo'],
                    'description' => $row['descripcion'] ?: null,
                ];

                if ($product) {
                    unset($attributes['slug']);
                    $product->update($attributes);
                    $result['updated']++;
                } else {
                    Product::query()->create(['sku' => $row['sku'], ...$attributes]);
                    $result['created']++;
                }
            }

            return $result;
        });
    }

    /** @return iterable<int, array<int, string>> */
    public function exportRows(): iterable
    {
        yield self::HEADERS;

        foreach (Product::query()->with(['category', 'brand'])->orderBy('name')->cursor() as $product) {
            yield [
                $product->sku, $product->name, $product->barcode ?? '', $product->category?->name ?? '',
                $product->brand?->name ?? '', $product->unit, $product->cost ?? '', $product->price ?? '',
                $product->minimum_stock, $product->notify_low_stock ? 'si' : 'no',
                $product->is_sellable ? 'si' : 'no', $product->is_active ? 'si' : 'no',
                $product->description ?? '',
            ];
        }
    }

    /** @return array<string, mixed> */
    private function validateRow(array $row, int $line): array
    {
        $row = array_map(fn ($value) => trim((string) $value), $row);
        $row['sku'] = Str::upper($row['sku']);

        if ($row['sku'] === '' || mb_strlen($row['sku']) > 80) {
            $this->fail($line, 'el SKU es obligatorio y admite hasta 80 caracteres');
        }
        if ($row['nombre'] === '' || mb_strlen($row['nombre']) > 255) {
            $this->fail($line, 'el nombre es obligatorio y admite hasta 255 caracteres');
        }
        if (! in_array($row['unidad'], ['unidad', 'kg', 'g', 'l', 'ml', 'caja'], true)) {
            $this->fail($line, 'unidad no válida');
        }
        foreach (['coste', 'precio'] as $field) {
            $row[$field] = $this->decimal($row[$field], $line, $field, true);
        }
        $row['stock_minimo'] = $this->decimal($row['stock_minimo'], $line, 'stock_minimo', false);
        foreach (['avisar_stock_bajo', 'disponible_venta', 'activo'] as $field) {
            $row[$field] = $this->boolean($row[$field], $line, $field);
        }

        if (mb_strlen($row['codigo_barras']) > 80 || mb_strlen($row['categoria']) > 255 || mb_strlen($row['marca']) > 255 || mb_strlen($row['descripcion']) > 5000) {
            $this->fail($line, 'uno de los textos supera la longitud permitida');
        }

        $row['_line'] = $line;

        return $row;
    }

    private function validateUniqueValues(array $rows): void
    {
        $skus = [];
        $barcodes = [];
        foreach ($rows as $row) {
            if (isset($skus[$row['sku']])) {
                $this->fail($row['_line'], "SKU repetido; también aparece en la línea {$skus[$row['sku']]}");
            }
            $skus[$row['sku']] = $row['_line'];

            if ($row['codigo_barras'] !== '') {
                if (isset($barcodes[$row['codigo_barras']])) {
                    $this->fail($row['_line'], 'código de barras repetido dentro del archivo');
                }
                $barcodes[$row['codigo_barras']] = $row['sku'];
                $conflict = Product::query()->where('barcode', $row['codigo_barras'])->where('sku', '!=', $row['sku'])->exists();
                if ($conflict) {
                    $this->fail($row['_line'], 'el código de barras ya pertenece a otro producto');
                }
            }
        }
    }

    private function decimal(string $value, int $line, string $field, bool $nullable): ?string
    {
        if ($value === '' && $nullable) {
            return null;
        }
        $normalized = str_replace(',', '.', $value === '' ? '0' : $value);
        if (! is_numeric($normalized) || (float) $normalized < 0) {
            $this->fail($line, "$field debe ser un número igual o mayor que cero");
        }

        return $normalized;
    }

    private function boolean(string $value, int $line, string $field): bool
    {
        return match (Str::lower($value)) {
            'si', 'sí', '1', 'true' => true,
            'no', '0', 'false', '' => false,
            default => $this->fail($line, "$field debe contener sí o no"),
        };
    }

    private function categoryId(string $name): ?int
    {
        return $name === '' ? null : Category::query()->firstOrCreate(
            ['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]
        )->id;
    }

    private function brandId(string $name): ?int
    {
        return $name === '' ? null : Brand::query()->firstOrCreate(
            ['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]
        )->id;
    }

    private function normalizeHeader(string $header): string
    {
        return Str::lower(trim(str_replace("\xEF\xBB\xBF", '', $header)));
    }

    private function fail(int $line, string $message): never
    {
        throw ValidationException::withMessages(['importFile' => "Línea {$line}: {$message}."]);
    }
}
