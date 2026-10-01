<?php

namespace App\Support;

use App\Models\AppModule;
use App\Models\User;

class ModuleManager
{
    public const MODULES = [
        'inventory' => ['label' => 'Inventario y compras', 'description' => 'Productos, almacenes, movimientos, lotes, proveedores, compras, conteos y etiquetas.'],
        'landing' => ['label' => 'Landing y catálogo público', 'description' => 'Web pública, catálogo, contenido, reseñas mediante QR y configuración de la landing.'],
        'reservations' => ['label' => 'Reservas', 'description' => 'Agenda, recursos, horarios, solicitudes públicas y configuración de reservas.'],
        'people' => ['label' => 'Personal y fichaje', 'description' => 'Empleados, puestos, horarios, asistencia, fichaje, ausencias y vacaciones.'],
        'sales' => ['label' => 'Ventas y TPV', 'description' => 'Punto de venta, pedidos, tickets, formas de pago y devolución segura de existencias.'],
        'payroll' => ['label' => 'Contratos y nóminas', 'description' => 'Expedientes contractuales, documentos privados, periodos de nómina, recibos PDF y autoservicio del empleado.'],
    ];

    private ?array $states = null;

    public function enabled(string $key): bool
    {
        return $this->states()[$key] ?? false;
    }

    public function all(): array
    {
        return collect(self::MODULES)->map(fn (array $module, string $key) => [
            ...$module, 'enabled' => $this->enabled($key),
        ])->all();
    }

    public function update(array $states, User $user): void
    {
        foreach (array_keys(self::MODULES) as $key) {
            AppModule::query()->updateOrCreate(['key' => $key], [
                'enabled' => (bool) ($states[$key] ?? false),
                'changed_by' => $user->id,
            ]);
        }
        $this->states = null;
    }

    private function states(): array
    {
        return $this->states ??= AppModule::query()->pluck('enabled', 'key')->map(fn ($value) => (bool) $value)->all()
            + array_fill_keys(array_keys(self::MODULES), true);
    }
}
