<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Inventory\ProductCsvController;
use App\Http\Controllers\People\AbsenceAttachmentController;
use App\Http\Controllers\People\AttendanceExportController;
use App\Http\Controllers\Site\LegalPageController;
use App\Livewire\Admin\AccessLogsIndex;
use App\Livewire\Admin\ActivityLogsIndex;
use App\Livewire\Admin\LandingSettings;
use App\Livewire\Admin\PlatformModules;
use App\Livewire\Admin\UsersIndex;
use App\Livewire\Dashboard;
use App\Livewire\Inventory\BrandsIndex;
use App\Livewire\Inventory\CategoriesIndex;
use App\Livewire\Inventory\CountShow;
use App\Livewire\Inventory\CountsIndex;
use App\Livewire\Inventory\LabelsIndex;
use App\Livewire\Inventory\LotsIndex;
use App\Livewire\Inventory\MovementsIndex;
use App\Livewire\Inventory\ProductsIndex;
use App\Livewire\Inventory\PurchaseOrderShow;
use App\Livewire\Inventory\PurchaseOrdersIndex;
use App\Livewire\Inventory\SupplierProducts;
use App\Livewire\Inventory\SuppliersIndex;
use App\Livewire\Inventory\TransfersIndex;
use App\Livewire\Inventory\WarehousesIndex;
use App\Livewire\NotificationsIndex;
use App\Livewire\People\AbsencesAdmin;
use App\Livewire\People\AttendanceIndex;
use App\Livewire\People\EmployeeSchedule;
use App\Livewire\People\EmployeesIndex;
use App\Livewire\People\JobPositionsIndex;
use App\Livewire\People\MyAbsences;
use App\Livewire\People\TimeClock;
use App\Livewire\Profile\NotificationSettings;
use App\Livewire\Public\LandingPage;
use App\Livewire\Public\ProductCatalog;
use App\Livewire\Public\SubmitTestimonial;
use App\Livewire\Reservations\ReservationSettings;
use App\Livewire\Reservations\ReservationsIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPage::class)->middleware('module:landing')->name('home');
Route::get('/productos', ProductCatalog::class)->middleware(['module:landing', 'module:inventory'])->name('public.products');
Route::get('/politica-de-privacidad', [LegalPageController::class, 'privacy'])->middleware('module:landing')->name('public.privacy');
Route::get('/aviso-legal', [LegalPageController::class, 'legalNotice'])->middleware('module:landing')->name('public.legal-notice');
Route::get('/opinar/{token}', SubmitTestimonial::class)->middleware(['module:landing', 'throttle:30,1'])->name('public.testimonials.submit');

Route::middleware('guest')->group(function (): void {
    Route::get('/iniciar-sesion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/iniciar-sesion', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/panel', Dashboard::class)->name('dashboard');
    Route::get('/notificaciones', NotificationsIndex::class)->name('notifications.index');
    Route::get('/mi-cuenta/notificaciones', NotificationSettings::class)->name('profile.notifications');
    Route::middleware('module:inventory')->group(function (): void {
        Route::get('/inventario/productos', ProductsIndex::class)
            ->middleware('can:inventory.view')
            ->name('inventory.products.index');
        Route::get('/inventario/productos/exportar', [ProductCsvController::class, 'export'])
            ->middleware('can:inventory.manage')
            ->name('inventory.products.export');
        Route::get('/inventario/productos/plantilla', [ProductCsvController::class, 'template'])
            ->middleware('can:inventory.manage')
            ->name('inventory.products.template');
        Route::get('/inventario/productos/exportar/excel', [ProductCsvController::class, 'excel'])
            ->middleware('can:inventory.manage')
            ->name('inventory.products.excel');
        Route::get('/inventario/productos/exportar/pdf', [ProductCsvController::class, 'pdf'])
            ->middleware('can:inventory.manage')
            ->name('inventory.products.pdf');
        Route::get('/inventario/categorias', CategoriesIndex::class)
            ->middleware('can:inventory.view')
            ->name('inventory.categories.index');
        Route::get('/inventario/marcas', BrandsIndex::class)
            ->middleware('can:inventory.view')
            ->name('inventory.brands.index');
        Route::get('/inventario/almacenes', WarehousesIndex::class)
            ->middleware('can:inventory.view')
            ->name('inventory.warehouses.index');
        Route::get('/inventario/movimientos', MovementsIndex::class)
            ->middleware('can:inventory.view')
            ->name('inventory.movements.index');
        Route::get('/inventario/transferencias', TransfersIndex::class)
            ->middleware('can:inventory.adjust')
            ->name('inventory.transfers.index');
        Route::get('/inventario/etiquetas', LabelsIndex::class)
            ->middleware('can:inventory.manage')
            ->name('inventory.labels.index');
        Route::get('/inventario/conteos', CountsIndex::class)
            ->middleware('can:inventory.count')
            ->name('inventory.counts.index');
        Route::get('/inventario/conteos/{inventoryCount}', CountShow::class)
            ->middleware('can:inventory.count')
            ->name('inventory.counts.show');
        Route::get('/inventario/proveedores', SuppliersIndex::class)
            ->middleware('can:inventory.manage')
            ->name('inventory.suppliers.index');
        Route::get('/inventario/proveedores/{supplier}/productos', SupplierProducts::class)
            ->middleware('can:inventory.manage')
            ->name('inventory.suppliers.products');
        Route::get('/inventario/lotes', LotsIndex::class)
            ->middleware('can:inventory.view')
            ->name('inventory.lots.index');
        Route::get('/inventario/ordenes-compra', PurchaseOrdersIndex::class)
            ->middleware('can:inventory.manage')
            ->name('inventory.purchase-orders.index');
        Route::get('/inventario/ordenes-compra/{purchaseOrder}', PurchaseOrderShow::class)
            ->middleware('can:inventory.manage')
            ->name('inventory.purchase-orders.show');
    });
    Route::get('/administracion/accesos', AccessLogsIndex::class)
        ->middleware('can:audit.view')
        ->name('admin.access-logs.index');
    Route::get('/administracion/operaciones', ActivityLogsIndex::class)
        ->middleware('can:audit.view')
        ->name('admin.activity-logs.index');
    Route::get('/administracion/usuarios', UsersIndex::class)
        ->middleware('can:users.manage')
        ->name('admin.users.index');
    Route::get('/administracion/landing', LandingSettings::class)
        ->middleware(['module:landing', 'can:content.manage'])
        ->name('admin.landing.settings');
    Route::get('/administracion/plataforma', PlatformModules::class)
        ->middleware('platform-owner')
        ->name('admin.platform.modules');
    Route::middleware('module:reservations')->group(function (): void {
        Route::get('/reservas', ReservationsIndex::class)
            ->middleware('can:reservations.view')
            ->name('reservations.index');
        Route::get('/reservas/configuracion', ReservationSettings::class)
            ->middleware('can:reservations.configure')
            ->name('reservations.settings');
    });
    Route::middleware('module:people')->group(function (): void {
        Route::get('/personal', EmployeesIndex::class)->middleware('can:people.view')->name('people.employees.index');
        Route::get('/personal/asistencia', AttendanceIndex::class)->middleware('can:people.manage')->name('people.attendance.index');
        Route::get('/personal/asistencia/excel', [AttendanceExportController::class, 'excel'])->middleware('can:people.manage')->name('people.attendance.excel');
        Route::get('/personal/asistencia/pdf', [AttendanceExportController::class, 'pdf'])->middleware('can:people.manage')->name('people.attendance.pdf');
        Route::get('/personal/ausencias', AbsencesAdmin::class)->middleware('can:people.manage')->name('people.absences.admin');
        Route::get('/mis-ausencias', MyAbsences::class)->middleware('can:people.clock')->name('people.absences.mine');
        Route::get('/ausencias/{absenceRequest}/justificante', AbsenceAttachmentController::class)->name('people.absences.attachment');
        Route::get('/personal/{employee}/horario', EmployeeSchedule::class)->middleware('can:people.manage')->name('people.employees.schedule');
        Route::get('/personal/puestos', JobPositionsIndex::class)->middleware('can:people.manage')->name('people.positions.index');
        Route::get('/mi-fichaje', TimeClock::class)->middleware('can:people.clock')->name('people.clock');
    });
    Route::post('/cerrar-sesion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
