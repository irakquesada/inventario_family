<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Inventory\ProductCsv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCsvController extends Controller
{
    public function export(ProductCsv $csv): StreamedResponse
    {
        return response()->streamDownload(function () use ($csv): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            foreach ($csv->exportRows() as $row) {
                fputcsv($output, array_map($this->safeCell(...), $row), ';', '"', '');
            }
            fclose($output);
        }, 'productos-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ProductCsv::HEADERS, ';', '"', '');
            fclose($output);
        }, 'plantilla-productos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function excel(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Productos');
            $headers = ['SKU', 'Producto', 'Código de barras', 'Categoría', 'Marca', 'Unidad', 'Stock total', 'Coste', 'Precio', 'Stock mínimo', 'Avisar', 'Venta', 'Estado'];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            foreach ($this->products() as $product) {
                foreach (['A' => $product->sku, 'B' => $product->name, 'C' => $product->barcode ?? '', 'D' => $product->category?->name ?? '', 'E' => $product->brand?->name ?? '', 'F' => $product->unit] as $column => $value) {
                    $sheet->setCellValueExplicit("{$column}{$row}", (string) $value, DataType::TYPE_STRING);
                }
                $sheet->fromArray([
                    (float) ($product->balances_sum_quantity ?? 0),
                    $product->cost === null ? null : (float) $product->cost,
                    $product->price === null ? null : (float) $product->price,
                    (float) $product->minimum_stock,
                    $product->notify_low_stock ? 'Sí' : 'No',
                    $product->is_sellable ? 'Sí' : 'No',
                    $product->is_active ? 'Activo' : 'Inactivo',
                ], null, "G{$row}");
                $row++;
            }

            $lastRow = max(1, $row - 1);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:M{$lastRow}");
            $sheet->getStyle('A1:M1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C2410C']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(24);
            foreach (['A' => 18, 'B' => 32, 'C' => 22, 'D' => 22, 'E' => 20, 'F' => 12, 'G' => 14, 'H' => 13, 'I' => 13, 'J' => 15, 'K' => 11, 'L' => 11, 'M' => 12] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            $sheet->getStyle("G2:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.000');
            $sheet->getStyle("A1:M{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'productos-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(): Response
    {
        return Pdf::loadView('pdf.products', [
            'products' => $this->products(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->download('productos-'.now()->format('Y-m-d').'.pdf');
    }

    private function products()
    {
        return Product::query()
            ->with(['category', 'brand'])
            ->withSum('balances', 'quantity')
            ->orderBy('name')
            ->get();
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
