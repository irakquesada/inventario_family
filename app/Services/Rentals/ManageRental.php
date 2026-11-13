<?php

namespace App\Services\Rentals;

use App\Models\InventoryBalance;
use App\Models\Rental;
use App\Models\RentalRate;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageRental
{
    public function start(RentalRate $rate, Warehouse $warehouse, User $actor, array $customer, float $quantity, string $startedAt, ?string $plannedEndsAt, ?string $notes): Rental
    {
        return DB::transaction(function () use ($rate, $warehouse, $actor, $customer, $quantity, $startedAt, $plannedEndsAt, $notes): Rental {
            $rate = RentalRate::query()->with('product')->lockForUpdate()->findOrFail($rate->id);
            if (! $rate->is_active || ! $rate->product->is_active || ! $rate->product->is_rentable || $quantity <= 0) {
                throw ValidationException::withMessages(['rentalRateId' => 'La tarifa o el producto ya no están disponibles.']);
            }
            $balance = InventoryBalance::query()->where('product_id', $rate->product_id)->where('warehouse_id', $warehouse->id)->lockForUpdate()->first();
            $stock = (float) ($balance?->quantity ?? 0);
            $rented = (float) Rental::query()->where('product_id', $rate->product_id)->where('warehouse_id', $warehouse->id)->where('status', 'active')->sum('quantity');
            if ($stock - $rented < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'No hay unidades suficientes disponibles en ese almacén.']);
            }
            $start = CarbonImmutable::parse($startedAt, config('app.timezone'));
            $plannedEnd = $plannedEndsAt ? CarbonImmutable::parse($plannedEndsAt, config('app.timezone')) : null;
            if ($plannedEnd && $plannedEnd->lte($start)) {
                throw ValidationException::withMessages(['plannedEndsAt' => 'La devolución prevista debe ser posterior a la salida.']);
            }
            $estimatedUnits = $plannedEnd ? $this->units($start, $plannedEnd, $rate->unit_minutes, $rate->minimum_units, $rate->grace_minutes, $rate->billing_unit) : null;
            $rental = Rental::query()->create([
                'reference' => (string) Str::uuid(), 'product_id' => $rate->product_id, 'warehouse_id' => $warehouse->id,
                'rental_rate_id' => $rate->id, 'created_by' => $actor->id, 'status' => 'active',
                'customer_name' => $customer['name'], 'customer_email' => $customer['email'] ?: null,
                'customer_phone' => $customer['phone'] ?: null, 'quantity' => $quantity, 'started_at' => $start,
                'planned_ends_at' => $plannedEnd, 'rate_price' => $rate->price, 'billing_unit' => $rate->billing_unit,
                'unit_minutes' => $rate->unit_minutes, 'minimum_units' => $rate->minimum_units,
                'grace_minutes' => $rate->grace_minutes, 'estimated_total' => $estimatedUnits === null ? null : round($estimatedUnits * (float) $rate->price * $quantity, 2),
                'notes' => $notes ?: null,
            ]);
            $rental->update(['number' => 'ALQ-'.now()->format('Ymd').'-'.str_pad((string) $rental->id, 6, '0', STR_PAD_LEFT)]);

            return $rental->fresh(['product', 'warehouse', 'rate']);
        });
    }

    public function finish(Rental $rental, User $actor, string $returnedAt, ?string $notes): Rental
    {
        return DB::transaction(function () use ($rental, $actor, $returnedAt, $notes): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->id);
            if ($rental->status !== 'active') {
                throw ValidationException::withMessages(['returningId' => 'El alquiler ya fue cerrado.']);
            }
            $returned = CarbonImmutable::parse($returnedAt, config('app.timezone'));
            if ($returned->lte($rental->started_at)) {
                throw ValidationException::withMessages(['returnedAt' => 'La devolución debe ser posterior a la salida.']);
            }
            $units = $this->units(CarbonImmutable::instance($rental->started_at), $returned, $rental->unit_minutes, $rental->minimum_units, $rental->grace_minutes, $rental->billing_unit);
            $rental->update([
                'status' => 'returned', 'returned_by' => $actor->id, 'returned_at' => $returned, 'billed_units' => $units,
                'final_total' => round($units * (float) $rental->rate_price * (float) $rental->quantity, 2), 'return_notes' => $notes ?: null,
            ]);

            return $rental->fresh();
        });
    }

    private function units(CarbonImmutable $start, CarbonImmutable $end, ?int $unitMinutes, int $minimumUnits, int $graceMinutes, string $billingUnit): int
    {
        if ($billingUnit === 'fixed') {
            return max(1, $minimumUnits);
        }
        $minutes = max(0, $start->diffInMinutes($end) - $graceMinutes);

        return max($minimumUnits, (int) ceil($minutes / max(1, (int) $unitMinutes)));
    }
}
