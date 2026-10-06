<?php

use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\ReceiptService;
use App\Services\ReportService;
use App\Support\AppSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
    $this->invoices = app(InvoiceService::class);
    $this->reports = app(ReportService::class);
    $this->product = Product::factory()->create(['default_price' => '0.3000']);
    AppSettings::setMany(['vat_enabled' => false]);
});

test('the monthly report splits sales by method, subtracts returns and computes net', function () {
    $customer = Customer::factory()->credit(null)->create();
    $year = (int) today()->format('Y');
    $month = (int) today()->format('n');

    // 300 cash, 150 credit, 60 mada this month.
    $cashInv = $this->invoices->postDirect(['customer_id' => $customer->id, 'payment_method' => 'cash', 'invoice_date' => today()->toDateString()], [['product_id' => $this->product->id, 'qty' => 1000]], $this->owner);
    $this->invoices->postDirect(['customer_id' => $customer->id, 'payment_method' => 'credit', 'invoice_date' => today()->toDateString(), 'override_credit' => true], [['product_id' => $this->product->id, 'qty' => 500]], $this->owner);
    $this->invoices->postDirect(['payment_method' => 'mada', 'source' => 'counter', 'invoice_date' => today()->toDateString()], [['product_id' => $this->product->id, 'qty' => 200]], $this->owner);

    // A 30.00 return against the cash invoice.
    $this->invoices->createCreditNote($cashInv, $customer, [['product_id' => $this->product->id, 'qty' => 100]], 'مرتجع', $this->owner);

    // Expenses: 100 cash + 50 bank.
    $category = ExpenseCategory::create(['name_ar' => 'غاز', 'kind' => 'operating']);
    $expenseService = app(ExpenseService::class);
    $expenseService->create(['expense_date' => today()->toDateString(), 'expense_category_id' => $category->id, 'amount' => '100', 'paid_from' => 'counter_cash'], $this->owner);
    $expenseService->create(['expense_date' => today()->toDateString(), 'expense_category_id' => $category->id, 'amount' => '50', 'paid_from' => 'bank'], $this->owner);

    // A 90.00 collection.
    app(ReceiptService::class)->create(['customer_id' => $customer->id, 'amount' => '90', 'receipt_date' => today()->toDateString(), 'method' => 'cash'], null, $this->owner);

    $row = collect($this->reports->monthly($year))->firstWhere('month', $month);

    expect($row['cash'])->toBe('270.00');     // 300 - 30 return
    expect($row['credit'])->toBe('150.00');
    expect($row['mada'])->toBe('60.00');
    expect($row['total'])->toBe('480.00');
    expect($row['collections'])->toBe('90.00');
    expect($row['expenses_total'])->toBe('150.00');
    expect($row['expenses_cash'])->toBe('100.00');
    expect($row['net'])->toBe('330.00');      // 480 - 150
    expect($row['net_cash'])->toBe('230.00'); // (270 + 60) - 100
});

test('the statement runs from the opening balance to the closing balance', function () {
    $customer = Customer::factory()->credit(null)->create();

    // Before the range: 60 credit sale.
    $this->invoices->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'credit', 'invoice_date' => today()->subDays(20)->toDateString(), 'override_credit' => true],
        [['product_id' => $this->product->id, 'qty' => 200]],
        $this->owner,
    );

    // Inside the range: +30 invoice, -40 receipt.
    $this->invoices->postDirect(
        ['customer_id' => $customer->id, 'payment_method' => 'credit', 'invoice_date' => today()->subDays(5)->toDateString(), 'override_credit' => true],
        [['product_id' => $this->product->id, 'qty' => 100]],
        $this->owner,
    );
    app(ReceiptService::class)->create(
        ['customer_id' => $customer->id, 'amount' => '40', 'receipt_date' => today()->subDays(3)->toDateString(), 'method' => 'cash'],
        null,
        $this->owner,
    );

    $statement = $this->reports->statement($customer, today()->subDays(10), today());

    expect($statement['opening'])->toBe('60.00');
    expect($statement['rows'])->toHaveCount(2);
    expect($statement['rows'][0]['balance'])->toBe('90.00');
    expect($statement['rows'][1]['balance'])->toBe('50.00');
    expect($statement['closing'])->toBe('50.00');
});

test('the signed public statement link works logged-out, a tampered one does not', function () {
    $customer = Customer::factory()->create();

    $url = URL::temporarySignedRoute('public.statement', now()->addDay(), [
        'customer' => $customer->id,
        'from' => today()->subMonth()->toDateString(),
        'to' => today()->toDateString(),
    ]);

    $this->get($url)->assertOk();

    // Breaking the signature kills the link.
    $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();

    // And the route without a signature is closed.
    $this->get(route('public.statement', ['customer' => $customer->id]))->assertForbidden();
});

test('inactive customers and weekly volume drops are detected', function () {
    $stopped = Customer::factory()->create(['name' => 'مطعم توقف']);
    $activeCustomer = Customer::factory()->create(['name' => 'مطعم مستمر']);
    $dropped = Customer::factory()->create(['name' => 'مطعم هبط']);

    // Stopped: last order 10 days ago. Active: today.
    $this->invoices->postDirect(['customer_id' => $stopped->id, 'payment_method' => 'cash', 'invoice_date' => today()->subDays(10)->toDateString()], [['product_id' => $this->product->id, 'qty' => 100]], $this->owner);
    $this->invoices->postDirect(['customer_id' => $activeCustomer->id, 'payment_method' => 'cash', 'invoice_date' => today()->toDateString()], [['product_id' => $this->product->id, 'qty' => 100]], $this->owner);

    // Dropped: 200 loaves last week, 80 this week -> 60% drop.
    $this->invoices->postDirect(['customer_id' => $dropped->id, 'payment_method' => 'cash', 'invoice_date' => today()->subDays(9)->toDateString()], [['product_id' => $this->product->id, 'qty' => 200]], $this->owner);
    $this->invoices->postDirect(['customer_id' => $dropped->id, 'payment_method' => 'cash', 'invoice_date' => today()->subDays(2)->toDateString()], [['product_id' => $this->product->id, 'qty' => 80]], $this->owner);

    $inactive = collect($this->reports->inactiveCustomers(3));
    expect($inactive->pluck('name'))->toContain('مطعم توقف');
    expect($inactive->pluck('name'))->not->toContain('مطعم مستمر');
    expect($inactive->pluck('name'))->not->toContain('مطعم هبط'); // ordered 2 days ago

    $drops = collect($this->reports->volumeDrops(30));
    expect($drops->pluck('name'))->toContain('مطعم هبط');
    $row = $drops->firstWhere('name', 'مطعم هبط');
    expect($row['previous'])->toBe(200);
    expect($row['current'])->toBe(80);
    expect($row['drop'])->toBe(60);
});

test('sales by route and by customer aggregate correctly', function () {
    $route = DeliveryRoute::factory()->create(['name' => 'خط الاختبار']);
    $customer = Customer::factory()->onRoute($route)->create(['name' => 'عميل الخط']);

    $this->invoices->postDirect(['customer_id' => $customer->id, 'payment_method' => 'cash'], [['product_id' => $this->product->id, 'qty' => 500]], $this->owner);

    $byRoute = collect($this->reports->salesByRoute(today()->subDay(), today()));
    $routeRow = $byRoute->firstWhere('name', 'خط الاختبار');
    expect($routeRow['qty'])->toBe(500);
    expect($routeRow['total'])->toBe('150.00');

    $byCustomer = collect($this->reports->salesByCustomer(today()->subDay(), today()));
    expect($byCustomer->firstWhere('name', 'عميل الخط')['qty'])->toBe(500);
});

test('clerks and drivers cannot open the reports screen', function () {
    $clerk = User::factory()->create();
    $clerk->assignRole('clerk');
    $driver = User::factory()->create();
    $driver->assignRole('driver');

    $this->actingAs($clerk)->get('/reports')->assertForbidden();
    $this->actingAs($driver)->get('/reports')->assertForbidden();

    $this->actingAs($this->owner)->get('/reports')->assertOk();
});
