<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeliveryRoute;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Price;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\RawMaterialMovement;
use App\Models\StandingOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\OrderGridService;
use App\Services\PayrollService;
use App\Services\ReceiptService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // --- Staff -----------------------------------------------------------------
        $accountant = User::firstOrCreate(
            ['email' => 'accountant@laheeb.test'],
            ['name' => 'محاسب المعمل', 'password' => 'password'],
        );
        $accountant->syncRoles(['accountant']);

        $clerk = User::firstOrCreate(
            ['email' => 'clerk@laheeb.test'],
            ['name' => 'مدخل الطلبات', 'password' => 'password'],
        );
        $clerk->syncRoles(['clerk']);

        $driverNames = ['أحمد السائق', 'محمد السائق', 'خالد السائق', 'سعيد السائق', 'فهد السائق', 'عمر السائق'];
        $drivers = [];
        foreach ($driverNames as $i => $name) {
            $n = $i + 1;
            $driver = User::firstOrCreate(
                ['email' => "driver{$n}@laheeb.test"],
                ['name' => $name, 'password' => 'password', 'phone' => '05000000'.str_pad((string) $n, 2, '0', STR_PAD_LEFT)],
            );
            $driver->syncRoles(['driver']);
            $drivers[] = $driver;
        }

        // --- Routes ----------------------------------------------------------------
        $routeDefs = [
            ['وسط الرياض', 'الرياض'],
            ['شمال الرياض', 'الرياض'],
            ['شرق الرياض', 'الرياض'],
            ['الخرج', 'الخرج'],
            ['المجمعة وسدير', 'المجمعة'],
            ['القصيم', 'بريدة'],
        ];

        $routes = [];
        foreach ($routeDefs as $i => [$name, $city]) {
            $routes[] = DeliveryRoute::firstOrCreate(
                ['name' => $name],
                ['city' => $city, 'default_driver_id' => $drivers[$i]->id, 'sort_order' => $i + 1],
            );
        }

        // --- Groups ----------------------------------------------------------------
        $groupDefs = [
            ['مجموعة مار', '20000', 'group'],
            ['مجموعة الطيبين', '15000', 'group'],
            ['مجموعة النخيل', '10000', 'branch'],
            ['مجموعة الريف', '8000', 'group'],
            ['مجموعة البركة', '12000', 'group'],
            ['مجموعة الوسام', '9000', 'branch'],
        ];

        $groups = [];
        foreach ($groupDefs as [$name, $limit, $scope]) {
            $groups[] = CustomerGroup::firstOrCreate(
                ['name' => $name],
                ['credit_limit' => $limit, 'credit_scope' => $scope],
            );
        }

        // --- Customers ---------------------------------------------------------------
        if (Customer::count() > 0) {
            $this->activity($drivers, $accountant, $clerk);

            return; // Master demo data already seeded.
        }

        $prefixes = ['مطعم', 'بوفيه', 'بقالة', 'مطعم', 'بوفيه', 'شركة'];
        $names = [
            'الريان', 'النور', 'السلام', 'الوطن', 'الخير', 'البستان', 'الواحة', 'الربيع',
            'الفيصل', 'النخلة', 'الصفا', 'المروة', 'الرائد', 'الشروق', 'الغروب', 'الأصيل',
            'المدينة', 'العزيزية', 'السويدي', 'الروضة', 'النسيم', 'الملز', 'الشفا', 'العليا',
            'الحمراء', 'اليرموك', 'القدس', 'عرفات', 'البادية', 'الديرة', 'طيبة', 'الوسام',
            'الزهور', 'الياسمين', 'الورود', 'الأمانة', 'البركة', 'الرزق', 'الخليج', 'نجد',
            'الدرعية', 'الفاخرية', 'المجد', 'الريادة', 'السنابل', 'القمح', 'الطازج', 'الفرن',
            'التنور', 'الضيافة', 'الكرم', 'الجود', 'المائدة', 'السفرة', 'اللقمة', 'الذواقة',
            'المذاق', 'النكهة', 'الشهية', 'اللذة',
        ];

        $priceOptions = ['0.2500', '0.2700', '0.3000', '0.3500', '0.4000'];
        $saj40 = Product::whereHas('category', fn ($q) => $q->where('name_ar', 'صاج'))->where('size_cm', 40)->first();
        $products = Product::where('active', true)->get();

        foreach ($names as $i => $name) {
            $route = $routes[$i % 6];
            $group = $i < 30 && $i % 5 === 0 ? $groups[intdiv($i, 5) % 6] : ($i < 30 && $i % 3 === 0 ? $groups[$i % 6] : null);
            $isCredit = $i % 2 === 0;

            $customer = Customer::create([
                'code' => (string) (100 + $i),
                'name' => $prefixes[$i % 6].' '.$name,
                'customer_group_id' => $group?->id,
                'type' => 'wholesale',
                'payment_term' => $isCredit ? 'credit' : 'cash',
                'credit_limit' => $isCredit && $group === null ? (string) (2000 + ($i % 7) * 1500) : null,
                'credit_days' => $isCredit ? 30 : null,
                'delivery_route_id' => $route->id,
                'stop_sequence' => intdiv($i, 6) + 1,
                'city' => $route->city,
                'phone' => '05'.str_pad((string) (10000000 + $i * 111), 8, '0', STR_PAD_LEFT),
            ]);

            Price::create([
                'customer_id' => $customer->id,
                'price' => $priceOptions[$i % 5],
                'effective_from' => today()->subMonths(3),
            ]);

            // A few customers pay a different price for the big saj loaf.
            if ($saj40 !== null && $i % 8 === 0) {
                Price::create([
                    'customer_id' => $customer->id,
                    'product_id' => $saj40->id,
                    'price' => '0.4500',
                    'effective_from' => today()->subMonths(3),
                ]);
            }

            // Standing orders for a third of the customers.
            if ($i % 3 === 0) {
                $customerProducts = $products->shuffle()->take(2);
                foreach ($customerProducts as $product) {
                    foreach (range(0, 6) as $weekday) {
                        if ($weekday === 5 && $i % 2 === 0) {
                            continue; // Some customers skip Friday.
                        }
                        StandingOrder::create([
                            'customer_id' => $customer->id,
                            'product_id' => $product->id,
                            'weekday' => $weekday,
                            'qty' => (3 + ($i % 10)) * 10,
                        ]);
                    }
                }
            }
        }

        $this->activity($drivers, $accountant, $clerk);
    }

    /**
     * Fills every screen with believable numbers: ~21 months of sales history
     * (for the monthly report and its year-over-year compare), 30 full days of
     * wholesale + counter activity up to TODAY, collections, expenses, raw
     * materials, vehicles, leaves, credit notes, pending approvals, a paid and
     * a draft payroll run, and today's order grid.
     *
     * @param  array<int, User>  $drivers
     */
    private function activity(array $drivers, User $accountant, User $clerk): void
    {
        if (Invoice::count() > 0) {
            return; // Activity already generated.
        }

        $invoices = app(InvoiceService::class);
        $receiptsService = app(ReceiptService::class);
        $expensesService = app(ExpenseService::class);
        $payroll = app(PayrollService::class);

        $owner = User::where('email', 'owner@laheeb.test')->first() ?? $accountant;

        // --- Employees ---------------------------------------------------------
        $jobs = [
            ['خباز', 'baker', 8, 2200],
            ['سائق', 'driver', 6, 2500],
            ['عامل', 'worker', 6, 1700],
        ];
        $employees = [];
        $driverIndex = 0;
        foreach ($jobs as [$label, $job, $count, $base]) {
            foreach (range(1, $count) as $n) {
                $employees[] = Employee::create([
                    'name_ar' => "{$label} {$n}",
                    'nationality' => ['مصري', 'باكستاني', 'بنغالي', 'سوداني'][($n + $count) % 4],
                    'job' => $job,
                    'basic_salary' => (string) ($base + ($n % 4) * 150),
                    'join_date' => today()->subYears(2)->addMonths($n),
                    'iqama_expiry' => today()->addDays(30 + $n * 40),
                    'user_id' => $job === 'driver' ? ($drivers[$driverIndex++]->id ?? null) : null,
                ]);
            }
        }

        // --- Suppliers ----------------------------------------------------------
        $flourSupplier = Supplier::firstOrCreate(['name' => 'مطاحن الدقيق الأولى']);
        Supplier::firstOrCreate(['name' => 'شركة أكياس الرياض']);

        $categories = ExpenseCategory::pluck('id', 'name_ar');
        $products = Product::where('active', true)->get();
        $customers = Customer::with('route')->get();

        $vehicles = $this->fleet($accountant);
        $this->materials($accountant);
        $this->leaves($employees, $accountant);

        $this->history($invoices, $expensesService, $owner, $accountant, $customers, $products, $categories);

        // --- 30 full days (up to today) of sales, collections and expenses --------
        foreach (range(30, 0) as $daysAgo) {
            $date = today()->subDays($daysAgo);

            foreach ($customers as $i => $customer) {
                // A realistic subset orders each day.
                if (($i + $daysAgo) % 3 === 0) {
                    continue;
                }

                $lineProducts = $products->slice(($i + $daysAgo) % max(1, $products->count() - 2), 2);
                $items = $lineProducts->map(fn ($p) => [
                    'product_id' => $p->id,
                    'qty' => 50 + (($i * 7 + $daysAgo * 13) % 250),
                ])->values()->all();

                // Cash customers occasionally pay the driver by mada terminal.
                $method = $customer->payment_term === 'cash' && ($i + $daysAgo) % 5 === 0
                    ? 'mada'
                    : $customer->payment_term;

                $invoices->postDirect([
                    'customer_id' => $customer->id,
                    'invoice_date' => $date->toDateString(),
                    'payment_method' => $method,
                    'source' => 'delivery',
                    'driver_id' => $customer->route?->default_driver_id,
                    'override_credit' => true,
                ], $items, $owner);
            }

            // Walk-in counter sales (POS): a few small tickets every day.
            foreach (range(1, 3) as $k) {
                $pos = $products->slice(($daysAgo * 3 + $k * 5) % max(1, $products->count() - 1), $k === 2 ? 2 : 1);
                $invoices->postDirect([
                    'invoice_date' => $date->toDateString(),
                    'payment_method' => $k === 3 ? 'mada' : 'cash',
                    'source' => 'counter',
                ], $pos->map(fn ($p) => [
                    'product_id' => $p->id,
                    'qty' => 5 + (($daysAgo * 7 + $k * 11) % 25),
                ])->values()->all(), $accountant);
            }

            // Collections on old credit, a few customers every day.
            if ($daysAgo < 28) {
                $debtors = $customers->filter(
                    fn ($c) => $c->payment_term === 'credit' && (float) LedgerEntry::balance(LedgerEntry::CUSTOMER, $c->id) > 500,
                )->take(3);

                foreach ($debtors as $debtor) {
                    $balance = LedgerEntry::balance(LedgerEntry::CUSTOMER, $debtor->id);
                    $receiptsService->create([
                        'customer_id' => $debtor->id,
                        'amount' => number_format((float) $balance * 0.6, 2, '.', ''),
                        'receipt_date' => $date->toDateString(),
                        'method' => $daysAgo % 5 === 0 ? 'transfer' : 'cash',
                        'received_by' => $debtor->route?->default_driver_id ?? $accountant->id,
                    ], null, $accountant);
                }
            }

            // Daily operating expenses.
            $daily = [
                ['طحين', 900 + ($daysAgo % 5) * 120, $daysAgo % 4 === 0 ? 'supplier_credit' : 'counter_cash'],
                ['غاز', 120 + ($daysAgo % 3) * 30, 'counter_cash'],
                ['ديزل', 80 + ($daysAgo % 4) * 25, 'driver_cash'],
            ];
            foreach ($daily as [$category, $amount, $source]) {
                if (! isset($categories[$category])) {
                    continue;
                }
                $expensesService->create([
                    'expense_date' => $date->toDateString(),
                    'expense_category_id' => $categories[$category],
                    'amount' => (string) $amount,
                    'paid_from' => $source,
                    'paid_by' => $source === 'driver_cash' ? ($drivers[$daysAgo % count($drivers)]->id ?? null) : null,
                    'supplier_id' => $source === 'supplier_credit' ? $flourSupplier->id : null,
                    'vehicle_id' => $category === 'ديزل' ? ($vehicles[$daysAgo % count($vehicles)]->id ?? null) : null,
                ], $accountant);
            }

            // Weekly garage visit, booked on the vehicle so its cost report fills.
            if ($daysAgo % 7 === 3 && isset($categories['مصاريف السيارات'])) {
                $expensesService->create([
                    'expense_date' => $date->toDateString(),
                    'expense_category_id' => $categories['مصاريف السيارات'],
                    'amount' => (string) (250 + ($daysAgo % 3) * 110),
                    'paid_from' => 'counter_cash',
                    'vehicle_id' => $vehicles[intdiv($daysAgo, 7) % count($vehicles)]->id,
                    'notes' => 'غيار زيت وفحص دوري',
                ], $accountant);
            }
        }

        // A couple of fixed expenses this month.
        foreach ([['إيجارات', 6000], ['كهرباء وإنترنت', 1400]] as [$category, $amount]) {
            if (isset($categories[$category])) {
                $expensesService->create([
                    'expense_date' => today()->startOfMonth()->toDateString(),
                    'expense_category_id' => $categories[$category],
                    'amount' => (string) $amount,
                    'paid_from' => 'bank',
                ], $accountant);
            }
        }

        // --- Returns: a few credit notes against recent invoices --------------------
        $returnable = Invoice::query()
            ->where('status', Invoice::STATUS_POSTED)
            ->where('payment_method', 'credit')
            ->whereNotNull('customer_id')
            ->whereDate('invoice_date', '>=', today()->subDays(6))
            ->whereDate('invoice_date', '<=', today()->subDays(1))
            ->with('lines')
            ->take(3)
            ->get();

        foreach ($returnable as $j => $invoice) {
            $line = $invoice->lines->first();
            if ($line === null) {
                continue;
            }
            $invoices->createCreditNote(
                $invoice,
                null,
                [['product_id' => $line->product_id, 'qty' => max(1, intdiv((int) $line->qty, 10))]],
                ['مرتجع — خبز متكسر أثناء النقل', 'مرتجع — كمية زائدة عن الطلب', 'مرتجع — خطأ في التوصيل'][$j % 3],
                $accountant,
                Carbon::parse($invoice->invoice_date)->addDay()->min(today()),
            );
        }

        // --- Items waiting for the owner's approval ---------------------------------
        if (isset($categories['ثلاجة'])) {
            $expensesService->create([
                'expense_date' => today()->subDays(2)->toDateString(),
                'expense_category_id' => $categories['ثلاجة'],
                'amount' => '2400',
                'paid_from' => 'counter_cash',
                'notes' => 'صيانة ثلاجة العرض الكبيرة',
            ], $clerk);
        }
        if (isset($categories['أخرى'])) {
            $expensesService->create([
                'expense_date' => today()->subDay()->toDateString(),
                'expense_category_id' => $categories['أخرى'],
                'amount' => '1750',
                'paid_from' => 'bank',
                'notes' => 'قطع غيار للفرن الآلي',
            ], $clerk);
        }

        // --- Advances and one fine ------------------------------------------------
        $payroll->createAdvance([
            'employee_id' => $employees[0]->id,
            'amount' => '1000', 'plan' => 'full', 'paid_from' => 'counter_cash',
            'advance_date' => today()->subDays(40)->toDateString(),
        ], $accountant);
        $payroll->createAdvance([
            'employee_id' => $employees[9]->id,
            'amount' => '3000', 'plan' => 'installment', 'installment_amount' => '500', 'paid_from' => 'bank',
            'advance_date' => today()->subDays(35)->toDateString(),
        ], $accountant);

        // Over twice the basic salary and requested by the clerk -> waits for the owner.
        $payroll->createAdvance([
            'employee_id' => $employees[14]->id,
            'amount' => '4500', 'plan' => 'installment', 'installment_amount' => '750', 'paid_from' => 'counter_cash',
            'advance_date' => today()->subDays(3)->toDateString(),
        ], $clerk);

        $fine = $payroll->createCharge([
            'employee_id' => $employees[9]->id,
            'type' => 'fine', 'amount' => '300',
            'notes' => 'مخالفة سرعة',
        ], $accountant);
        $payroll->approveCharge($fine, $accountant);

        // --- Payroll: last month fully paid, this month still a draft ----------------
        $run = $payroll->createRun(today()->subMonth()->format('Y-m'), $accountant);
        $payroll->review($run, $accountant);
        $payroll->approve($run, $owner);
        $payroll->pay($run, [], $owner);

        $payroll->createRun(today()->format('Y-m'), $accountant);

        // --- Today's order grid, filled from the standing orders ---------------------
        app(OrderGridService::class)->fill(today(), 'standing', $clerk->id);
    }

    /**
     * ~21 months of lighter counter sales (cash + mada, no drivers, so neither
     * custody nor receivables carry old noise) plus the matching monthly bills.
     * Feeds the monthly report, its prev-year compare and the yearly totals.
     *
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $products
     * @param  Collection<string, int>  $categories
     */
    private function history(
        InvoiceService $invoices,
        ExpenseService $expensesService,
        User $owner,
        User $accountant,
        $customers,
        $products,
        $categories,
    ): void {
        $end = today()->subDays(31);
        $cursor = today()->copy()->subYear()->startOfYear();
        $monthIndex = 0;

        while ($cursor->lte($end)) {
            $monthIndex++;
            $grow = 1 + $monthIndex * 0.02; // The business grows a little every month.

            foreach ([2, 4, 7, 9, 11, 14, 16, 18, 21, 23, 25, 28] as $d) {
                $date = $cursor->copy()->day(min($d, $cursor->daysInMonth));
                if ($date->gt($end)) {
                    break;
                }

                foreach (range(0, 7) as $k) {
                    $customer = $customers[($d * 5 + $k * 7 + $monthIndex) % $customers->count()];
                    $lineProducts = $products->slice(($d + $k + $monthIndex) % max(1, $products->count() - 2), 2);

                    $items = $lineProducts->map(fn ($p) => [
                        'product_id' => $p->id,
                        'qty' => (int) round((450 + (($k * 83 + $d * 47) % 650)) * $grow),
                    ])->values()->all();

                    $invoices->postDirect([
                        'customer_id' => $customer->id,
                        'invoice_date' => $date->toDateString(),
                        'payment_method' => $k % 3 === 0 ? 'cash' : 'mada',
                        'source' => 'counter',
                        'override_credit' => true,
                    ], $items, $owner);
                }
            }

            // The month's bills, roughly in step with its sales.
            $bills = [
                ['طحين', (int) round(11000 * $grow), 'counter_cash'],
                ['غاز', (int) round(2200 * $grow), 'counter_cash'],
                ['إيجارات', 6000, 'bank'],
                ['كهرباء وإنترنت', 1300, 'bank'],
                ['أكياس مطبوعة', (int) round(900 * $grow), 'bank'],
            ];
            foreach ($bills as [$category, $amount, $source]) {
                if (! isset($categories[$category])) {
                    continue;
                }
                $expensesService->create([
                    'expense_date' => $cursor->copy()->day(min(5, $cursor->daysInMonth))->toDateString(),
                    'expense_category_id' => $categories[$category],
                    'amount' => (string) $amount,
                    'paid_from' => $source,
                ], $accountant);
            }

            $cursor->addMonth()->startOfMonth();
        }
    }

    /**
     * Six delivery vans with a believable maintenance log: one due within days,
     * one already overdue, the rest comfortably scheduled.
     *
     * @return array<int, Vehicle>
     */
    private function fleet(User $accountant): array
    {
        $defs = [
            ['فان وسط الرياض', 'أ ب ج 4821'],
            ['فان شمال الرياض', 'د هـ و 1973'],
            ['فان شرق الرياض', 'ز ح ط 6254'],
            ['فان الخرج', 'ي ك ل 3318'],
            ['فان المجمعة', 'م ن س 7742'],
            ['فان القصيم', 'ع ف ص 9106'],
        ];

        // Latest row per vehicle decides due-soon: index 0 => due in 3 days,
        // index 1 => already overdue, the rest 3-10 weeks out.
        $nextDue = [3, -2, 21, 35, 52, 70];

        $vehicles = [];
        foreach ($defs as $i => [$name, $plate]) {
            $vehicle = Vehicle::firstOrCreate(['name' => $name], ['plate' => $plate]);
            $vehicles[] = $vehicle;

            VehicleMaintenance::create([
                'vehicle_id' => $vehicle->id,
                'service_date' => today()->subDays(120 + $i * 9),
                'task' => 'تغيير زيت وفلاتر',
                'odometer' => 80000 + $i * 21000,
                'next_due_date' => today()->subDays(60 + $i * 9),
                'next_due_odometer' => 85000 + $i * 21000,
                'created_by' => $accountant->id,
            ]);

            VehicleMaintenance::create([
                'vehicle_id' => $vehicle->id,
                'service_date' => today()->subDays(25 + $i * 4),
                'task' => $i % 2 === 0 ? 'تغيير زيت وفلاتر' : 'فحص فرامل وإطارات',
                'odometer' => 86000 + $i * 21000,
                'next_due_date' => today()->addDays($nextDue[$i]),
                'next_due_odometer' => 91000 + $i * 21000,
                'notes' => $i === 1 ? 'تأخرت عن موعد الصيانة — تحتاج حجز هذا الأسبوع' : null,
                'created_by' => $accountant->id,
            ]);
        }

        return $vehicles;
    }

    /**
     * Eight raw materials with two months of purchases and consumption.
     * Flour and yeast end up below their reorder level on purpose, so the
     * low-stock warning has something true to say.
     */
    private function materials(User $accountant): void
    {
        // [name, unit, reorder level, opening qty, weekly in, weekly out]
        $defs = [
            ['دقيق أبيض فاخر', 'كيس 45 كجم', '80', 150, 60, 72],
            ['دقيق بر', 'كيس 45 كجم', '40', 100, 20, 21],
            ['خميرة فورية', 'كرتون', '15', 40, 6, 9.5],
            ['ملح', 'كيس 25 كجم', '10', 45, 3, 4],
            ['سكر', 'كيس 50 كجم', '10', 30, 2, 3],
            ['زيت نباتي', 'تنكة 17 لتر', '20', 80, 8, 10.5],
            ['سمسم', 'كيس 10 كجم', '8', 25, 2, 2.75],
            ['أكياس تغليف مطبوعة', 'ربطة 1000 كيس', '30', 110, 15, 19],
        ];

        foreach ($defs as $sort => [$name, $unit, $reorder, $opening, $in, $out]) {
            $material = RawMaterial::firstOrCreate(
                ['name' => $name],
                ['unit' => $unit, 'reorder_level' => $reorder, 'sort_order' => $sort + 1],
            );

            RawMaterialMovement::create([
                'raw_material_id' => $material->id,
                'movement_date' => today()->subWeeks(9),
                'direction' => 'in',
                'qty' => (string) $opening,
                'notes' => 'رصيد افتتاحي للمخزون',
                'created_by' => $accountant->id,
            ]);

            foreach (range(8, 1) as $week) {
                RawMaterialMovement::create([
                    'raw_material_id' => $material->id,
                    'movement_date' => today()->subWeeks($week),
                    'direction' => 'in',
                    'qty' => (string) $in,
                    'notes' => 'توريد أسبوعي',
                    'created_by' => $accountant->id,
                ]);

                // Consumption lands twice a week.
                foreach ([5, 2] as $offset) {
                    RawMaterialMovement::create([
                        'raw_material_id' => $material->id,
                        'movement_date' => today()->subWeeks($week)->addDays(7 - $offset),
                        'direction' => 'out',
                        'qty' => number_format($out / 2, 2, '.', ''),
                        'notes' => 'صرف للإنتاج',
                        'created_by' => $accountant->id,
                    ]);
                }
            }
        }
    }

    /**
     * Three leave stories: one finished normally, one running, one overdue.
     *
     * @param  array<int, Employee>  $employees
     */
    private function leaves(array $employees, User $accountant): void
    {
        EmployeeLeave::create([
            'employee_id' => $employees[2]->id, // خباز 3
            'start_date' => today()->subDays(65),
            'expected_return' => today()->subDays(35),
            'actual_return' => today()->subDays(33),
            'notes' => 'إجازة سنوية — عاد بتأخير يومين',
            'created_by' => $accountant->id,
        ]);

        EmployeeLeave::create([
            'employee_id' => $employees[16]->id, // عامل 3
            'start_date' => today()->subDays(5),
            'expected_return' => today()->addDays(25),
            'notes' => 'إجازة سنوية',
            'created_by' => $accountant->id,
        ]);

        EmployeeLeave::create([
            'employee_id' => $employees[15]->id, // عامل 2
            'start_date' => today()->subDays(40),
            'expected_return' => today()->subDays(10),
            'notes' => 'سفر طارئ — لم يعد حتى الآن',
            'created_by' => $accountant->id,
        ]);
    }
}
