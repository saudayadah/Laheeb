<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DeliveryRoute;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Price;
use App\Models\Product;
use App\Models\StandingOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\PayrollService;
use App\Services\ReceiptService;
use Illuminate\Database\Seeder;

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
            $this->activity($drivers, $accountant);

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
        $saj40 = Product::where('category', 'saj')->where('size_cm', 40)->first();
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

        $this->activity($drivers, $accountant);
    }

    /**
     * 30 days of invoices, collections, expenses, plus employees, advances
     * and a paid payroll run — so every screen and report has real numbers.
     *
     * @param  array<int, User>  $drivers
     */
    private function activity(array $drivers, User $accountant): void
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

        // --- 30 days of sales, collections and expenses --------------------------
        foreach (range(30, 1) as $daysAgo) {
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

                $invoices->postDirect([
                    'customer_id' => $customer->id,
                    'invoice_date' => $date->toDateString(),
                    'payment_method' => $customer->payment_term,
                    'source' => 'delivery',
                    'driver_id' => $customer->route?->default_driver_id,
                    'override_credit' => true,
                ], $items, $owner);
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

        $fine = $payroll->createCharge([
            'employee_id' => $employees[9]->id,
            'type' => 'fine', 'amount' => '300',
            'notes' => 'مخالفة سرعة',
        ], $accountant);
        $payroll->approveCharge($fine, $accountant);

        // --- A completed payroll run for last month --------------------------------
        $run = $payroll->createRun(today()->subMonth()->format('Y-m'), $accountant);
        $payroll->review($run, $accountant);
        $payroll->approve($run, $owner);
        $payroll->pay($run, [], $owner);
    }
}
