<?php

namespace App\Livewire\Rentals;

use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalInvitation;
use App\Models\RentalRate;
use App\Models\Warehouse;
use App\Notifications\RentalConfirmationNotification;
use App\Services\Rentals\ManageRental;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RentalsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

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

    public ?int $editingRentalId = null;

    public ?int $deletingRentalId = null;

    public string $deleteReason = '';

    public string $invitationLabel = '';

    public string $invitationDays = '7';

    public string $invitationUses = '1';

    public ?string $generatedUrl = null;

    public ?string $qrDataUri = null;

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

    public function editRental(int $id): void
    {
        $this->manage();
        $rental = Rental::query()->whereIn('status', ['reserved', 'active'])->findOrFail($id);
        $this->editingRentalId = $rental->id;
        $this->rentalRateId = (string) $rental->rental_rate_id;
        $this->warehouseId = (string) $rental->warehouse_id;
        $this->customerName = $rental->customer_name;
        $this->customerEmail = (string) $rental->customer_email;
        $this->customerPhone = (string) $rental->customer_phone;
        $this->quantity = (string) $rental->quantity;
        $this->startedAt = $rental->started_at->format('Y-m-d\TH:i');
        $this->plannedEndsAt = $rental->planned_ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->notes = (string) $rental->notes;
    }

    public function updateRental(ManageRental $manager): void
    {
        $this->manage();
        $data = $this->rentalData();
        $rental = $manager->update(Rental::query()->findOrFail($this->editingRentalId), RentalRate::query()->findOrFail($data['rentalRateId']), Warehouse::query()->findOrFail($data['warehouseId']), auth()->user(), ['name' => $data['customerName'], 'email' => $data['customerEmail'], 'phone' => $data['customerPhone']], (float) $data['quantity'], $data['startedAt'], $data['plannedEndsAt'] ?: null, $data['notes']);
        $this->cancelEdit();
        session()->flash('status', 'Alquiler '.$rental->number.' actualizado; requiere nueva revisión antes de enviar la confirmación.');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingRentalId', 'rentalRateId', 'customerName', 'customerEmail', 'customerPhone', 'plannedEndsAt', 'notes']);
        $this->quantity = '1';
        $this->startedAt = now()->format('Y-m-d\TH:i');
    }

    public function activateRental(int $id, ManageRental $manager): void
    {
        $this->manage();
        $rental = $manager->activate(Rental::query()->findOrFail($id), auth()->user());
        session()->flash('status', 'Reserva '.$rental->number.' entregada e iniciada.');
    }

    public function confirmAndNotify(int $id): void
    {
        $this->manage();
        $rental = Rental::query()->with(['product', 'rate', 'warehouse'])->findOrFail($id);
        if (! $rental->customer_email) {
            $this->addError('email', 'El alquiler no tiene correo de cliente.');

            return;
        }
        $rental->update(['confirmed_at' => now(), 'confirmed_by' => auth()->id()]);
        try {
            Notification::route('mail', [$rental->customer_email => $rental->customer_name])->notify(new RentalConfirmationNotification($rental));
            $rental->update(['confirmation_sent_at' => now()]);
            session()->flash('status', 'Datos revisados y confirmación enviada a '.$rental->customer_email.'.');
        } catch (\Throwable $exception) {
            Log::error('No se pudo enviar la confirmación del alquiler.', ['rental_id' => $rental->id, 'exception' => $exception->getMessage()]);
            session()->flash('error', 'Los datos quedaron revisados, pero el correo no pudo enviarse. Puedes reintentarlo sin perder la reserva.');
        }
    }

    public function beginDelete(int $id): void
    {
        $this->manage();
        $rental = Rental::query()->whereIn('status', ['reserved', 'cancelled'])->findOrFail($id);
        if ($rental->payments()->exists()) {
            $this->addError('deleteReason', 'No se puede eliminar una reserva con cobros. Reembolsa primero sus ventas asociadas.');

            return;
        }
        $this->deletingRentalId = $rental->id;
        $this->deleteReason = '';
    }

    public function deleteRental(): void
    {
        $this->manage();
        $this->validate(['deletingRentalId' => ['required', 'integer'], 'deleteReason' => ['required', 'string', 'min:5', 'max:500']]);
        $rental = Rental::query()->whereIn('status', ['reserved', 'cancelled'])->findOrFail($this->deletingRentalId);
        $rental->update(['notes' => trim(($rental->notes ? $rental->notes."\n" : '').'Motivo de eliminación: '.$this->deleteReason)]);
        $rental->delete();
        $this->reset(['deletingRentalId', 'deleteReason']);
        session()->flash('status', 'Reserva anulada. Se conserva en auditoría.');
    }

    public function generateInvitation(): void
    {
        $this->manage();
        $data = $this->validate(['invitationLabel' => ['nullable', 'string', 'max:100'], 'invitationDays' => ['required', 'integer', 'min:1', 'max:90'], 'invitationUses' => ['required', 'integer', 'min:1', 'max:200']]);
        $token = Str::random(64);
        RentalInvitation::query()->create(['token_hash' => hash('sha256', $token), 'label' => $data['invitationLabel'] ?: null, 'created_by' => auth()->id(), 'max_submissions' => $data['invitationUses'], 'expires_at' => now()->addDays((int) $data['invitationDays'])]);
        $this->generatedUrl = route('public.rentals.submit', $token);
        $this->qrDataUri = (new Builder(writer: new SvgWriter, data: $this->generatedUrl, encoding: new Encoding('UTF-8'), errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 360, margin: 16, roundBlockSizeMode: RoundBlockSizeMode::Margin))->build()->getDataUri();
        $this->invitationLabel = '';
    }

    public function revokeInvitation(int $id): void
    {
        $this->manage();
        RentalInvitation::query()->findOrFail($id)->update(['revoked_at' => now()]);
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
            ->withSum(['rentals as rented_quantity' => fn ($query) => $query->where('warehouse_id', $this->warehouseId ?: 0)->whereIn('status', ['reserved', 'active'])], 'quantity')
            ->orderBy('name')->get();
        $nextReturns = Rental::query()->where('warehouse_id', $this->warehouseId ?: 0)->whereIn('status', ['reserved', 'active'])->whereNotNull('planned_ends_at')
            ->selectRaw('product_id, MIN(planned_ends_at) as next_return')->groupBy('product_id')->pluck('next_return', 'product_id');

        return view('livewire.rentals.rentals-index', [
            'rentals' => Rental::query()->with(['product', 'warehouse', 'rate', 'payments.sale'])->withSum('payments', 'amount')->when($this->status, fn ($q) => $q->where('status', $this->status))->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$this->search.'%')->orWhere('customer_name', 'like', '%'.$this->search.'%')->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$this->search.'%'))))->latest()->paginate(20),
            'rates' => RentalRate::query()->with('product')->orderBy('name')->get(),
            'products' => $products,
            'nextReturns' => $nextReturns,
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'invitations' => RentalInvitation::query()->with('creator')->latest()->limit(12)->get(),
        ])->layout('layouts.app', ['title' => 'Alquileres']);
    }

    private function manage(): void
    {
        abort_unless(auth()->user()?->can('rentals.manage'), 403);
    }

    private function rentalData(): array
    {
        return $this->validate(['rentalRateId' => ['required', 'integer', 'exists:rental_rates,id'], 'warehouseId' => ['required', 'integer', 'exists:warehouses,id'], 'customerName' => ['required', 'string', 'max:255'], 'customerEmail' => ['nullable', 'email', 'max:255'], 'customerPhone' => ['nullable', 'string', 'max:40'], 'quantity' => ['required', 'numeric', 'gt:0'], 'startedAt' => ['required', 'date_format:Y-m-d\TH:i'], 'plannedEndsAt' => ['nullable', 'date_format:Y-m-d\TH:i'], 'notes' => ['nullable', 'string', 'max:1000']]);
    }
}
