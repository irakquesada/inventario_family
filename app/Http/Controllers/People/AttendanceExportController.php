<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Services\People\AttendanceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportController extends Controller
{
    public function excel(Request $request, AttendanceReport $report): StreamedResponse
    {
        [$data, $rows] = $this->data($request, $report);

        return response()->streamDownload(function () use ($rows): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Asistencia');
            $sheet->fromArray(['Fecha', 'Empleado', 'Puesto', 'Horario', 'Primera entrada', 'Última salida', 'Horas previstas', 'Horas trabajadas', 'Incidencias'], null, 'A1');
            $rowNumber = 2;
            foreach ($rows as $row) {
                $sheet->fromArray([
                    $row['date']->format('d/m/Y'), $row['employee']->name, $row['employee']->jobPosition?->name ?? '',
                    $row['periods']->map(fn ($period) => substr($period->starts_at, 0, 5).'-'.substr($period->ends_at, 0, 5))->implode(' / ') ?: 'Descanso',
                    $row['first_in']?->format('H:i:s') ?? '', $row['last_out']?->format('H:i:s') ?? '',
                    round($row['planned_minutes'] / 60, 2), round($row['worked_minutes'] / 60, 2),
                    collect($row['incidents'])->map(fn ($incident) => $this->incidentLabel($incident))->implode(', ') ?: 'Sin incidencias',
                ], null, "A{$rowNumber}");
                $rowNumber++;
            }
            $lastRow = max(1, $rowNumber - 1);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:I{$lastRow}");
            $sheet->getStyle('A1:I1')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C2410C']]]);
            foreach (['A' => 13, 'B' => 28, 'C' => 22, 'D' => 25, 'E' => 15, 'F' => 15, 'G' => 16, 'H' => 17, 'I' => 30] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, "asistencia-{$data['from']}-{$data['to']}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(Request $request, AttendanceReport $report): Response
    {
        [$data, $rows] = $this->data($request, $report);

        return Pdf::loadView('pdf.attendance', ['rows' => $rows, 'filters' => $data, 'generatedAt' => now()])
            ->setPaper('a4', 'landscape')->download("asistencia-{$data['from']}-{$data['to']}.pdf");
    }

    private function data(Request $request, AttendanceReport $report): array
    {
        $data = $request->validate([
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['all', 'ok', 'missing', 'open', 'late', 'incomplete', 'unplanned', 'corrected', 'excused'])],
        ]);
        abort_if(CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) > 62, 422, 'El intervalo máximo es de 62 días.');

        return [$data, $report->rows($data['from'], $data['to'], isset($data['employee_id']) ? (int) $data['employee_id'] : null, $data['status'] ?? 'all')];
    }

    private function incidentLabel(string $incident): string
    {
        return ['missing' => 'Sin fichaje', 'open' => 'Jornada abierta', 'late' => 'Entrada tardía', 'incomplete' => 'Horas insuficientes', 'unplanned' => 'Trabajo en descanso', 'corrected' => 'Corregido', 'excused' => 'Ausencia justificada'][$incident] ?? $incident;
    }
}
