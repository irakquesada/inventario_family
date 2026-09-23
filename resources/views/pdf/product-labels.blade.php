<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8mm; }
        body { color: #1c1917; font-family: DejaVu Sans, sans-serif; margin: 0; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        tr { page-break-inside: avoid; }
        td { padding: 2mm; vertical-align: top; width: {{ 100 / $columns }}%; }
        .label { border: 0.25mm dashed #a8a29e; box-sizing: border-box; height: {{ $columns === 3 ? '31mm' : '42mm' }}; overflow: hidden; padding: 2.5mm; text-align: center; }
        .brand { color: #9a3412; font-size: 7pt; font-weight: bold; }
        .name { font-size: {{ $columns === 3 ? '8pt' : '10pt' }}; font-weight: bold; height: 6mm; margin-top: 1mm; overflow: hidden; }
        .barcode { height: {{ $columns === 3 ? '10mm' : '14mm' }}; margin: 1mm auto 0; max-width: 95%; width: auto; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: 7pt; letter-spacing: 0.4pt; }
        .price { font-size: 10pt; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        @foreach($rows as $row)
            <tr>
                @foreach($row as $label)
                    <td><div class="label"><div class="brand">{{ config('brand.name') }}</div><div class="name">{{ $label['product']->name }}</div><img class="barcode" src="{{ $label['image'] }}"><div class="code">{{ $label['value'] }}</div>@if($showPrice && $label['product']->price !== null)<div class="price">{{ number_format((float)$label['product']->price, 2, ',', '.') }} {{ config('brand.currency') }}</div>@endif</div></td>
                @endforeach
                @for($empty = $row->count(); $empty < $columns; $empty++)<td></td>@endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
