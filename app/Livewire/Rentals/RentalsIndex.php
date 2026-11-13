<?php

namespace App\Livewire\Rentals;

use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalRate;
use App\Models\Warehouse;
use App\Services\Rentals\ManageRental;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RentalsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'active';

    #[Url]
    public string $search = '';

    public ?int $editingRateId = null;

    public string $rateProductId = '';

    public string $rateName = '';

    public string $billingUnit = 'hour';

    public string $ratePrice = '';

    public string $minimumUnits = '1';

    public string $graceMinutes = '0';

    public string $rentalRateId = '';

    public string $warehouseId = '';

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $quantity = '1';

    public string $startedAt = '';

    public string $plannedEndsAt = '';

    public string $notes = '';

    public ?int $returningId = null;

    public string $returnedAt = '';

    public string $returnNotes = '';

    public string $scan = '';

    public function mount(): void
    {
        $this->warehouseId = (string) Warehouse::query()->where('is_active', true)->value('id');
        $this->startedAt = now()->format('Y-m-d\TH:i');
    }

    public function saveRate(): void
    {
        $this->manage();
        $data = $this->validate(['rateProductId' => ['required', 'integer', 'exists:products,id'], 'rateName' => ['required', 'string', 'max:255'], 'billingUnit' => ['required', Rule::in(['minute', 'hour', 'day', 'fixed'])], 'ratePrice' => ['required', 'numeric', 'min:0.01'], 'minimumUnits' => ['required', 'integer', 'min:1', 'max:10000'], 'graceMinutes' => ['required', 'integer', 'min:0', 'max:1440']]);
        $product = Product::query()->findOrFail($data['rateProductId']);
        abort_unless($product->is_rentable, 422);
        RentalRate::query()->updateOrCreate(['id' => $this->editingRateId], ['product_id' => $product->id, 'name' => $data['rateName'], 'billing_unit' => $data['billingUnit'], 'unit_minutes' => ['minute' => 1, 'hour' => 60, 'day' => 1440, 'fixed' => null][$data['billingUnit']], 'price' => $data['ratePrice'], 'minimum_units' => $data['minimumUnits'], 'grace_minutes' => $data['graceMinutes'], 'is_active' => true]);
        $this->resetRate();
        session()->flash('status', 'Tarifa guardada.');
    }

    public function editRate(int $id): void
    {
        $this->manage();
        $rate = RentalRate::query()->findOrFail($id);
        $this->editingRateId = $rate->id;
        $this->rateProductId = (string) $rate->product_id;
        $this->rateName = $rate->name;
        $this->billingUnit = $rate->billing_unit;
        $this->ratePrice = (string) $rate->price;
        $this->minimumUnits = (string) $rate->minimum_units;
        $this->graceMinutes = (string) $rate->grace_minutes;
    }

    public function resetRate(): void
    {
        $this->reset(['editingRateId', 'rateProductId', 'rateName', 'ratePrice']);
        $this->billingUnit = 'hour';
        $this->minimumUnits = '1';
        $this->graceMinutes = '0';
    }

    public function startRental(ManageRental $manager): void
    {
        $this->manage();
        $data = $this->validate(['rentalRateId' => ['required', 'integer', 'exists:rental_rates,id'], 'warehouseId' => ['required', 'integer', 'exists:warehouses,id'], 'customerName' => ['required', 'string', 'max:255'], 'customerEmail' => ['nullable', 'email', 'max:255'], 'customerPhone' => ['nullable', 'string', 'max:40'], 'quantity' => ['required', 'numeric', 'gt:0'], 'startedAt' => ['required', 'date_format:Y-m-d\TH:i'], 'plannedEndsAt' => ['nullable', 'date_format:Y-m-d\TH:i'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $rental = $manager->start(RentalRate::query()->findOrFail($data['rentalRateId']), Warehouse::query()->findOrFail($data['warehouseId']), auth()->user(), ['name' => $data['customerName'], 'email' => $data['customerEmail'], 'phone' => $data['customerPhone']], (float) $data['quantity'], $data['startedAt'], $data['plannedEndsAt'] ?: null, $data['notes']);
        $this->reset(['rentalRateId', 'customerName', 'customerEmail', 'customerPhone', 'plannedEndsAt', 'notes']);
        $this->quantity = '1';
        $this->startedAt = now()->format('Y-m-d\TH:i');
        session()->flash('status', 'Alquiler '.$rental->number.' iniciado.');
    }

    public function beginReturn(int $id): void
    {
        $this->manage();
        $this->returningId = Rental::query()->where('status', 'active')->findOrFail($id)->id;
        $this->returnedAt = now()->format('Y-m-d\TH:i');
        $this->returnNotes = '';
    }

    public function finishRental(ManageRental $manager): void
    {
        $this->manage();
        $data = $this->validate(['returningId' => ['required', 'integer', 'exists:rentals,id'], 'returnedAt' => ['required', 'date_format:Y-m-d\TH:i'], 'returnNotes' => ['nullable', 'string', 'max:1000']]);
        $rental = $manager->finish(Rental::query()->findOrFail($data['returningId']), auth()->user(), $data['returnedAt'], $data['returnNotes']);
        $this->reset(['returningId', 'returnedAt', 'returnNotes']);
        session()->flash('status', 'Alquiler '.$rental->number.' devuelto. Total: '.number_format((float) $rental->final_total, 2, ',', '.').' €.');
    }

    public function findForReturn(): void
    {
        $this->manage();
        $value = trim($this->scan);
        if ($value === '') {
            return;
        }
        $rental = Rental::query()->where('status', 'active')->where(fn ($q) => $q->where('number', $value)->orWhere('reference', $value)->orWhereHas('product', fn ($p) => $p->where('barcode', $value)->orWhere('sku', $value)))->latest()->first();
        if (! $rental) {
            $this->addError('scan', 'No se encontró un alquiler activo.');

            return;
        }
        $this->beginReturn($rental->id);
        $this->scan = '';
    }

    public function render()
    {
        $products = Product::query()->where('is_active', true)->where('is_rentable', true)
            ->withSum(['balances as rental_stock' => fn ($query) => $query->where('warehouse_id', $this->warehouseId ?: 0)], 'quantity')
            ->withSum(['rentals as rented_quantity' => fn ($query) => $query->where('warehouse_id', $this->warehouseId ?: 0)->where('status', 'active')], 'quantity')
            ->orderBy('name')->get();
        $nextReturns = Rental::query()->where('warehouse_id', $this->warehouseId ?: 0)->where('status', 'active')->whereNotNull('planned_ends_at')
            ->selectRaw('product_id, MIN(planned_ends_at) as next_return')->groupBy('product_id')->pluck('next_return', 'product_id');

        return view('livewire.rentals.rentals-index', [
            'rentals' => Rental::query()->with(['product', 'warehouse', 'rate'])->when($this->status, fn ($q) => $q->where('status', $this->status))->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$this->search.'%')->orWhere('customer_name', 'like', '%'.$this->search.'%')->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$this->search.'%'))))->latest()->paginate(20),
            'rates' => RentalRate::query()->with('product')->orderBy('name')->get(),
            'products' => $products,
            'nextReturns' => $nextReturns,
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Alquileres']);
    }

    private function manage(): void
    {
        abort_unless(auth()->user()?->can('rentals.manage'), 403);
    }
}
