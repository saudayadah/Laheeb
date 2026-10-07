<?php

use App\Http\Controllers\AdvanceController;
use App\Http\Controllers\BakerySettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DailyCloseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryRouteController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseSheetController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReceivablesController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Services\Imports\ImporterRegistry;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::post('locale', [LocaleController::class, 'update'])
    ->middleware('throttle:10,1')
    ->name('locale.update');

// Customers open their statement through a time-limited signed link.
Route::get('public/statement/{customer}', [StatementController::class, 'publicShow'])->name('public.statement');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'grid'])->name('grid');
        Route::post('cells', [OrderController::class, 'saveCells'])->name('cells');
        Route::post('fill', [OrderController::class, 'fill'])->name('fill');
        Route::get('production', [OrderController::class, 'production'])->name('production');
        Route::get('loading', [OrderController::class, 'loading'])->name('loading');
    });

    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::post('confirm-day', [InvoiceController::class, 'confirmDay'])->name('confirm-day');
        Route::get('{invoice}', [InvoiceController::class, 'show'])->name('show');
        Route::post('{invoice}/void', [InvoiceController::class, 'void'])->name('void');
        Route::post('{invoice}/reclassify', [InvoiceController::class, 'reclassify'])->name('reclassify');
        Route::post('{invoice}/credit-note', [InvoiceController::class, 'creditNote'])->name('credit-note');
        Route::get('{invoice}/thermal', [InvoiceController::class, 'thermal'])->name('thermal');
        Route::get('{invoice}/print', [InvoiceController::class, 'print'])->name('print');
    });

    Route::prefix('delivery')->name('delivery.')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');
        Route::get('stops/{customer}', [DeliveryController::class, 'stop'])->name('stop');
        Route::post('stops/{customer}', [DeliveryController::class, 'postStop'])->name('post-stop');
        Route::post('collect/{customer}', [DeliveryController::class, 'collect'])->name('collect');
        Route::post('expense', [DeliveryController::class, 'storeExpense'])->name('expense');
    });

    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::get('sheet', [ExpenseSheetController::class, 'index'])->name('sheet');
        Route::get('sheet/export', [ExpenseSheetController::class, 'export'])->name('sheet.export');
        Route::get('categories', [ExpenseCategoryController::class, 'index'])->name('categories');
        Route::post('categories', [ExpenseCategoryController::class, 'store'])->name('categories.store');
        Route::patch('categories/{category}', [ExpenseCategoryController::class, 'update'])->name('categories.update');
        Route::post('recurring', [ExpenseCategoryController::class, 'storeRecurring'])->name('recurring.store');
        Route::patch('recurring/{recurring}', [ExpenseCategoryController::class, 'updateRecurring'])->name('recurring.update');
        Route::post('{expense}/approve', [ExpenseController::class, 'approve'])->name('approve');
        Route::post('{expense}/void', [ExpenseController::class, 'void'])->name('void');
        Route::get('{expense}/photo', [ExpenseController::class, 'photo'])->name('photo');
    });

    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::get('{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::patch('{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::post('{supplier}/pay', [SupplierController::class, 'pay'])->name('pay');
    });

    Route::prefix('receipts')->name('receipts.')->group(function () {
        Route::get('/', [ReceiptController::class, 'index'])->name('index');
        Route::get('open-invoices', [ReceiptController::class, 'openInvoices'])->name('open-invoices');
        Route::post('/', [ReceiptController::class, 'store'])->name('store');
        Route::get('{receipt}/voucher', [ReceiptController::class, 'voucher'])->name('voucher');
        Route::post('{receipt}/void', [ReceiptController::class, 'void'])->name('void');
    });

    Route::get('receivables', [ReceivablesController::class, 'index'])->name('receivables.index');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/monthly/export', [ReportController::class, 'exportMonthly'])->name('reports.monthly.export');
    Route::get('customers/{customer}/statement', [StatementController::class, 'show'])->name('statements.show');
    Route::get('customers/{customer}/statement/pdf', [StatementController::class, 'pdf'])->name('statements.pdf');

    Route::prefix('closes')->name('closes.')->group(function () {
        Route::get('/', [DailyCloseController::class, 'index'])->name('index');
        Route::get('create', [DailyCloseController::class, 'create'])->name('create');
        Route::post('/', [DailyCloseController::class, 'store'])->name('store');
        Route::post('{close}/approve', [DailyCloseController::class, 'approve'])->name('approve');
    });

    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('sale', [PosController::class, 'sale'])->name('sale');
        Route::post('daily-retail', [PosController::class, 'dailyRetail'])->name('daily-retail');
    });

    Route::prefix('materials')->name('materials.')->group(function () {
        Route::get('/', [RawMaterialController::class, 'index'])->name('index');
        Route::post('/', [RawMaterialController::class, 'store'])->name('store');
        Route::post('movements', [RawMaterialController::class, 'storeMovement'])->name('movements.store');
        Route::patch('{material}', [RawMaterialController::class, 'update'])->name('update');
    });

    Route::prefix('vehicles')->name('vehicles.')->group(function () {
        Route::get('/', [VehicleController::class, 'index'])->name('index');
        Route::post('/', [VehicleController::class, 'store'])->name('store');
        Route::post('maintenance', [VehicleController::class, 'storeMaintenance'])->name('maintenance.store');
        Route::patch('{vehicle}', [VehicleController::class, 'update'])->name('update');
    });

    Route::prefix('leaves')->name('leaves.')->group(function () {
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::post('/', [LeaveController::class, 'store'])->name('store');
        Route::post('{leave}/return', [LeaveController::class, 'markReturned'])->name('return');
    });

    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::patch('{employee}', [EmployeeController::class, 'update'])->name('update');
    });

    Route::prefix('advances')->name('advances.')->group(function () {
        Route::get('/', [AdvanceController::class, 'index'])->name('index');
        Route::post('/', [AdvanceController::class, 'store'])->name('store');
        Route::post('charges', [AdvanceController::class, 'storeCharge'])->name('charges.store');
        Route::post('charges/{charge}/approve', [AdvanceController::class, 'approveCharge'])->name('charges.approve');
        Route::post('{advance}/approve', [AdvanceController::class, 'approve'])->name('approve');
    });

    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::post('/', [PayrollController::class, 'store'])->name('store');
        Route::get('{run}', [PayrollController::class, 'show'])->name('show');
        Route::get('{run}/sheet', [PayrollController::class, 'sheet'])->name('sheet');
        Route::get('lines/{line}/payslip', [PayrollController::class, 'payslip'])->name('payslip');
        Route::patch('lines/{line}', [PayrollController::class, 'updateLine'])->name('lines.update');
        Route::post('{run}/review', [PayrollController::class, 'review'])->name('review');
        Route::post('{run}/approve', [PayrollController::class, 'approve'])->name('approve');
        Route::post('{run}/reopen', [PayrollController::class, 'reopen'])->name('reopen');
        Route::post('{run}/pay', [PayrollController::class, 'pay'])->name('pay');
        Route::delete('{run}', [PayrollController::class, 'destroy'])->name('destroy');
    });

    Route::resource('customers', CustomerController::class)->except(['show']);

    Route::resource('groups', CustomerGroupController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['groups' => 'group']);

    Route::resource('delivery-routes', DeliveryRouteController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['delivery-routes' => 'delivery_route']);

    Route::post('products/categories', [ProductController::class, 'storeCategory'])->name('products.categories.store');
    Route::patch('products/categories/{category}', [ProductController::class, 'updateCategory'])->name('products.categories.update');

    Route::resource('products', ProductController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::post('prices', [PriceController::class, 'store'])->name('prices.store');
    Route::delete('prices/{price}', [PriceController::class, 'destroy'])->name('prices.destroy');

    Route::resource('users', UserController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('settings/bakery', [BakerySettingsController::class, 'edit'])->name('settings.bakery.edit');
    Route::patch('settings/bakery', [BakerySettingsController::class, 'update'])->name('settings.bakery.update');

    Route::get('settings/mail', [MailSettingsController::class, 'edit'])->name('settings.mail.edit');
    Route::patch('settings/mail', [MailSettingsController::class, 'update'])->name('settings.mail.update');
    Route::post('settings/mail/test', [MailSettingsController::class, 'test'])
        ->middleware('throttle:5,1')
        ->name('settings.mail.test');

    Route::prefix('imports')->name('imports.')->group(function () {
        $types = ImporterRegistry::types();

        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::get('{type}/template', [ImportController::class, 'template'])->whereIn('type', $types)->name('template');
        Route::post('{type}/preview', [ImportController::class, 'preview'])->whereIn('type', $types)->name('preview');
        Route::post('{type}/commit', [ImportController::class, 'commit'])->whereIn('type', $types)->name('commit');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
