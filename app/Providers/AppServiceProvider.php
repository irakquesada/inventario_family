<?php

namespace App\Providers;

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\AccessLog;
use App\Models\AppModule;
use App\Models\Brand;
use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Employee;
use App\Models\EmployeeAbsenceBalance;
use App\Models\EmployeeWorkPeriod;
use App\Models\InventoryCount;
use App\Models\JobPosition;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\Reservation;
use App\Models\ReservationResource;
use App\Models\SiteSetting;
use App\Models\Supplier;
use App\Models\Testimonial;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\AuditObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            User::class, AppModule::class, Category::class, Brand::class, Product::class, Warehouse::class,
            Supplier::class, ProductSupplier::class, InventoryCount::class,
            PurchaseOrder::class, PurchaseOrderLine::class, PurchaseReceipt::class,
            ReservationResource::class, BusinessHour::class, BusinessClosure::class, Reservation::class,
            SiteSetting::class, Testimonial::class,
            Employee::class, EmployeeWorkPeriod::class, TimeEntry::class,
            JobPosition::class,
            AbsenceType::class, AbsenceRequest::class, EmployeeAbsenceBalance::class,
        ] as $model) {
            $model::observe(AuditObserver::class);
        }

        Event::listen(Login::class, function (Login $event): void {
            AccessLog::query()->create([
                'user_id' => $event->user->getAuthIdentifier(),
                'event' => 'login',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => request()->hasSession() ? request()->session()->getId() : null,
                'occurred_at' => now(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            AccessLog::query()->create([
                'user_id' => $event->user?->getAuthIdentifier(),
                'event' => 'logout',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => request()->hasSession() ? request()->session()->getId() : null,
                'occurred_at' => now(),
            ]);
        });
    }
}
