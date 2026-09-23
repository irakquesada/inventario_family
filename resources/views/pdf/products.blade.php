<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 70px 28px 38px; }
        body { color: #292524; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        header { left: 28px; position: fixed; right: 28px; top: -52px; }
        h1 { color: #9a3412; font-size: 17px; margin: 0 0 3px; }
        header p { color: #78716c; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #c2410c; color: white; font-size: 7px; padding: 6px 5px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e7e5e4; padding: 6px 5px; vertical-align: top; }
        tbody tr:nth-child(even) { background: #fafaf9; }
        .number { text-align: right; white-space: nowrap; }
        .muted { color: #78716c; }
        footer { bottom: -25px; color: #78716c; left: 28px; position: fixed; right: 28px; text-align: center; }
        footer .page:after { content: counter(page); }
    </style>
</head>
<body>
    <header><h1>{{ config('brand.name') }} · Catálogo de productos</h1><p>Generado el {{ $generatedAt->format('d/m/Y H:i') }} · {{ $products->count() }} productos</p></header>
    <footer>Página <span class="page"></span></footer>
    <table>
        <thead><tr><th>Producto</th><th>SKU / código</th><th>Categoría</th><th>Marca</th><th>Unidad</th><th class="number">Stock</th><th class="number">Coste</th><th class="number">Precio</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse($products as $product)
                <tr><td><strong>{{ $product->name }}</strong></td><td>{{ $product->sku }}<br><span class="muted">{{ $product->barcode ?: 'Sin código' }}</span></td><td>{{ $product->category?->name ?? '—' }}</td><td>{{ $product->brand?->name ?? '—' }}</td><td>{{ $product->unit }}</td><td class="number">{{ number_format((float)($product->balances_sum_quantity ?? 0), 3, ',', '.') }}</td><td class="number">{{ $product->cost === null ? '—' : number_format((float)$product->cost, 2, ',', '.') }}</td><td class="number">{{ $product->price === null ? '—' : number_format((float)$product->price, 2, ',', '.') }}</td><td>{{ $product->is_active ? 'Activo' : 'Inactivo' }}</td></tr>
            @empty<tr><td colspan="9">No hay productos.</td></tr>@endforelse
        </tbody>
    </table>
</body>
</html>
