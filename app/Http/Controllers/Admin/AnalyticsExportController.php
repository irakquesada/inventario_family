<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicAnalyticsEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $from = $request->date('from', today()->subDays(29))?->startOfDay();
        $to = $request->date('to', today())?->endOfDay();

        return response()->streamDownload(function () use ($from, $to): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Fecha', 'Evento', 'Ruta', 'Elemento', 'Producto', 'Dispositivo', 'Origen'], ';');
            PublicAnalyticsEvent::query()->with('product')->whereBetween('occurred_at', [$from, $to])->oldest('occurred_at')
                ->chunkById(500, function ($events) use ($output): void {
                    foreach ($events as $event) {
                        fputcsv($output, [$event->occurred_at->format('d/m/Y H:i:s'), $event->event_type, $event->path, $event->label, $event->product?->name, $event->device_type, $event->referrer_host], ';');
                    }
                });
            fclose($output);
        }, 'analitica-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
