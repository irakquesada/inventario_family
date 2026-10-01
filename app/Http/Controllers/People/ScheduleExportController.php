<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Services\People\WeeklyScheduleReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScheduleExportController extends Controller
{
    public function __invoke(Request $request, WeeklyScheduleReport $report): Response
    {
        $data = $request->validate(['week' => ['required', 'date']]);
        $week = CarbonImmutable::parse($data['week'])->startOfWeek()->toDateString();

        return Pdf::loadView('pdf.weekly-schedule', $report->data($week) + ['generatedAt' => now()])
            ->setPaper('a4', 'landscape')->download("cuadrante-semanal-{$week}.pdf");
    }
}
