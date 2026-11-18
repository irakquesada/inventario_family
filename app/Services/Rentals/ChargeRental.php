<?php

namespace App\Services\Rentals;

use App\Events\SaleChanged;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChargeRental
{
    public function handle(Rental $rental, User $user, float $amount, string $paymentMethod): Sale
    {
        if (! in_array($paymentMethod, ['cash', 'card', 'transfer', 'other'], true)) {
            throw ValidationException::withMessages(['rentalPaymentMethod' => 'Selecciona una forma de pago válida.']);
        }

        $sale = DB::transaction(function () use ($rental, $user, $amount, $paymentMethod): Sale {
            $rental = Rental::query()->withSum('payments', 'amount')->lockForUpdate()->findOrFail($rental->id);
            if (! in_array($rental->status, ['reserved', 'active', 'returned'], true)) {
                throw ValidationException::withMessages(['rentalPayment' => 'Este alquiler no admite cobros.']);
            }
            $amount = round($amount, 2);
            $due = $rental->amountDue();
            if ($amount <= 0 || $amount > $due + 0.001) {
                throw ValidationException::withMessages(['rentalPaymentAmount' => 'El importe debe ser mayor que cero y no superar el saldo pendiente.']);
            }
            $sale = Sale::query()->create([
                'reference' => (string) Str::uuid(), 'warehouse_id' => $rental->warehouse_id, 'user_id' => $user->id,
                'status' => 'completed', 'channel' => 'rental', 'payment_method' => $paymentMethod,
                'customer_name' => $rental->customer_name, 'customer_email' => $rental->customer_email,
                'customer_phone' => $rental->customer_phone, 'subtotal' => $amount, 'discount' => 0, 'total' => $amount,
                'notes' => 'Cobro del alquiler '.$rental->number, 'confirmed_at' => now(), 'completed_at' => now(),
            ]);
            $sale->update(['number' => 'V-'.now()->format('Ymd').'-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);
            $sale->items()->create([
                'product_id' => $rental->product_id, 'rental_id' => $rental->id, 'product_name' => 'Alquiler · '.$rental->product->name,
                'product_sku' => $rental->product->sku, 'unit' => 'servicio', 'quantity' => 1, 'unit_price' => $amount,
                'unit_cost' => null, 'line_total' => $amount,
            ]);
            RentalPayment::query()->create(['rental_id' => $rental->id, 'sale_id' => $sale->id, 'created_by' => $user->id, 'amount' => $amount, 'kind' => $amount + 0.001 >= $due ? 'settlement' : 'deposit']);
            $sale->statusHistory()->create(['user_id' => $user->id, 'to_status' => 'completed', 'notes' => 'Cobro aplicado al alquiler '.$rental->number.'.']);

            DB::afterCommit(function () use ($sale): void {
                try {
                    SaleChanged::dispatch($sale);
                } catch (\Throwable $exception) {
                    Log::warning('No se pudo emitir el cobro de alquiler en tiempo real.', ['sale_id' => $sale->id, 'exception' => $exception->getMessage()]);
                }
            });

            return $sale;
        });

        return $sale->load(['items', 'warehouse', 'user']);
    }
}
