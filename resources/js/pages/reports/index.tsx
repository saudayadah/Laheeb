import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtInt, fmtPrice } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface MonthRow {
    month: number;
    total: string;
    cash: string;
    credit: string;
    mada: string;
    collections: string;
    expenses_total: string;
    expenses_cash: string;
    expenses_bank: string;
    salaries: string;
    rent: string;
    net: string;
    net_cash: string;
}

interface NamedTotal {
    name: string;
    code?: string;
    qty: number;
    total: string;
}

interface ReportsPageProps {
    year: number;
    monthly: MonthRow[];
    monthlyPrev: MonthRow[];
    from: string;
    to: string;
    salesByProduct: NamedTotal[];
    salesByCustomer: NamedTotal[];
    salesByRoute: NamedTotal[];
    collectionsByDriver: { name: string; total: string; cash: string; count: number }[];
    custodyByDriver: { name: string; balance: string }[];
    inactiveDays: number;
    inactiveCustomers: {
        id: number;
        code: string;
        name: string;
        phone: string | null;
        route: string | null;
        last_invoice: string | null;
        balance: string;
    }[];
    dropPercent: number;
    volumeDrops: { id: number; name: string; previous: number; current: number; drop: number }[];
}

type Tab = 'monthly' | 'sales' | 'customers' | 'drivers';

export default function ReportsIndex(props: ReportsPageProps) {
    const { t } = useTrans();
    const [tab, setTab] = useState<Tab>('monthly');

    const tabs: { key: Tab; label: string }[] = [
        { key: 'monthly', label: t('reports.tab.monthly') },
        { key: 'sales', label: t('reports.tab.sales') },
        { key: 'customers', label: t('reports.tab.customers') },
        { key: 'drivers', label: t('reports.tab.drivers') },
    ];

    return (
        <AppLayout breadcrumbs={[{ title: t('reports.title'), href: '/reports' }]}>
            <Head title={t('reports.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={t('reports.title')} />

                <div className="flex flex-wrap gap-1 rounded-xl border p-1 print:hidden">
                    {tabs.map(({ key, label }) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setTab(key)}
                            className={`rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                tab === key ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'monthly' && <MonthlySection {...props} />}
                {tab === 'sales' && <SalesSection {...props} />}
                {tab === 'customers' && <CustomersSection {...props} />}
                {tab === 'drivers' && <DriversSection {...props} />}
            </div>
        </AppLayout>
    );
}

function RangeBar({ from, to, extra }: { from: string; to: string; extra?: Record<string, string | number> }) {
    const { t } = useTrans();
    const apply = (updates: Record<string, string | number>) =>
        router.get(route('reports.index'), { from, to, ...extra, ...updates }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <div className="flex flex-wrap items-center gap-2 print:hidden">
            <Input
                type="date"
                dir="ltr"
                className="w-38"
                value={from}
                aria-label={t('common.from')}
                onChange={(e) => apply({ from: e.target.value })}
            />
            <span className="text-muted-foreground">—</span>
            <Input type="date" dir="ltr" className="w-38" value={to} aria-label={t('common.to')} onChange={(e) => apply({ to: e.target.value })} />
        </div>
    );
}

function MonthlySection({ year, monthly, monthlyPrev }: ReportsPageProps) {
    const { t, locale } = useTrans();

    const totals = useMemo(() => {
        const sum = (rows: MonthRow[], key: keyof MonthRow) => rows.reduce((acc, row) => acc + parseFloat(String(row[key])), 0);
        return {
            total: sum(monthly, 'total'),
            prevTotal: sum(monthlyPrev, 'total'),
            expenses: sum(monthly, 'expenses_total'),
            net: sum(monthly, 'net'),
            netCash: sum(monthly, 'net_cash'),
        };
    }, [monthly, monthlyPrev]);

    const expensePct = totals.total > 0 ? Math.round((totals.expenses / totals.total) * 100) : 0;
    const monthName = (m: number) =>
        new Date(2000, m - 1).toLocaleString(locale === 'ar' ? 'ar' : 'en', { month: locale === 'ar' ? 'long' : 'short' });
    const changeYear = (value: string) => router.get(route('reports.index'), { year: value }, { preserveState: false });

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">
                    {t('reports.monthly')} — {year}
                </h2>
                <div className="flex items-center gap-2 print:hidden">
                    <Input
                        type="number"
                        dir="ltr"
                        className="w-24"
                        defaultValue={year}
                        min={2020}
                        max={2100}
                        aria-label={t('reports.year')}
                        onChange={(e) => e.target.value.length === 4 && changeYear(e.target.value)}
                    />
                    <Button variant="outline" size="sm" asChild>
                        <a href={route('reports.monthly.export', { year })}>
                            <Download className="size-4" /> {t('expenses.export')}
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => window.print()}>
                        <Printer className="size-4" /> {t('orders.print')}
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div className="border-primary/30 bg-primary/10 dark:bg-primary/15 rounded-xl border p-4">
                    <div className="text-2xl font-bold tabular-nums">{fmtAmount(totals.total)}</div>
                    <div className="text-muted-foreground text-xs">
                        {t('reports.total')} {year}
                        {totals.prevTotal > 0 && (
                            <span
                                className={`ms-2 ${totals.total >= totals.prevTotal ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}
                            >
                                {totals.total >= totals.prevTotal ? '▲' : '▼'}
                                {Math.abs(Math.round(((totals.total - totals.prevTotal) / totals.prevTotal) * 100))}%
                            </span>
                        )}
                    </div>
                </div>
                <div className="rounded-xl border p-4">
                    <div className="text-2xl font-bold tabular-nums">{fmtAmount(totals.expenses)}</div>
                    <div className="text-muted-foreground text-xs">
                        {t('reports.expenses')}{' '}
                        <span className="ms-1">
                            ({expensePct}% {t('reports.expense_pct')})
                        </span>
                    </div>
                </div>
                <div className="rounded-xl border p-4">
                    <div
                        className={`text-2xl font-bold tabular-nums ${totals.net < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'}`}
                    >
                        {fmtAmount(totals.net)}
                    </div>
                    <div className="text-muted-foreground text-xs">{t('reports.net')}</div>
                </div>
                <div className="rounded-xl border p-4">
                    <div className="text-2xl font-bold tabular-nums">{fmtAmount(totals.netCash)}</div>
                    <div className="text-muted-foreground text-xs">{t('reports.net_cash')}</div>
                </div>
            </div>

            <p className="text-muted-foreground text-xs">{t('reports.net_cash_hint')}</p>

            <YearlyChart year={year} monthly={monthly} monthlyPrev={monthlyPrev} monthName={monthName} />

            <div className="overflow-auto rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('reports.month')}</TableHead>
                            <TableHead className="text-end">{t('reports.total')}</TableHead>
                            <TableHead className="text-end">{t('invoices.method.cash')}</TableHead>
                            <TableHead className="text-end">{t('invoices.method.credit')}</TableHead>
                            <TableHead className="text-end">{t('invoices.method.mada')}</TableHead>
                            <TableHead className="text-end">{t('reports.collections')}</TableHead>
                            <TableHead className="text-end">{t('reports.expenses')}</TableHead>
                            <TableHead className="text-end">{t('reports.salaries')}</TableHead>
                            <TableHead className="text-end">{t('reports.rent')}</TableHead>
                            <TableHead className="text-end">{t('reports.net')}</TableHead>
                            <TableHead className="text-end">{t('reports.net_cash')}</TableHead>
                            <TableHead className="text-muted-foreground text-end">{year - 1}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {monthly.map((row) => {
                            const prev = monthlyPrev[row.month - 1];
                            const hasData = parseFloat(row.total) !== 0 || parseFloat(row.expenses_total) !== 0;
                            return (
                                <TableRow key={row.month} className={hasData ? '' : 'opacity-60'}>
                                    <TableCell className="font-medium">{monthName(row.month)}</TableCell>
                                    <TableCell className="text-end font-semibold tabular-nums">{fmtAmount(row.total)}</TableCell>
                                    <TableCell className="text-end tabular-nums">{fmtAmount(row.cash)}</TableCell>
                                    <TableCell className="text-end text-amber-700 tabular-nums dark:text-amber-400">
                                        {fmtAmount(row.credit)}
                                    </TableCell>
                                    <TableCell className="text-end tabular-nums">{fmtAmount(row.mada)}</TableCell>
                                    <TableCell className="text-end text-emerald-700 tabular-nums dark:text-emerald-400">
                                        {fmtAmount(row.collections)}
                                    </TableCell>
                                    <TableCell className="text-end tabular-nums">{fmtAmount(row.expenses_total)}</TableCell>
                                    <TableCell className="text-muted-foreground text-end tabular-nums">{fmtAmount(row.salaries)}</TableCell>
                                    <TableCell className="text-muted-foreground text-end tabular-nums">{fmtAmount(row.rent)}</TableCell>
                                    <TableCell
                                        className={`text-end font-semibold tabular-nums ${parseFloat(row.net) < 0 ? 'text-red-600 dark:text-red-400' : ''}`}
                                    >
                                        {fmtAmount(row.net)}
                                    </TableCell>
                                    <TableCell className="text-end tabular-nums">{fmtAmount(row.net_cash)}</TableCell>
                                    <TableCell className="text-muted-foreground text-end tabular-nums">{prev ? fmtAmount(prev.total) : ''}</TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

/** Current vs previous year, month by month. Current year wears the sales color; last year is a neutral reference. */
function YearlyChart({
    year,
    monthly,
    monthlyPrev,
    monthName,
}: {
    year: number;
    monthly: MonthRow[];
    monthlyPrev: MonthRow[];
    monthName: (m: number) => string;
}) {
    const { t } = useTrans();

    const data = useMemo(
        () =>
            monthly.map((row) => ({
                month: row.month,
                current: parseFloat(row.total),
                prev: parseFloat(monthlyPrev[row.month - 1]?.total ?? '0'),
            })),
        [monthly, monthlyPrev],
    );

    const hasPrev = data.some((d) => d.prev !== 0);
    if (data.every((d) => d.current === 0) && !hasPrev) return null;

    return (
        <div className="rounded-xl border p-4">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">{t('reports.year_compare')}</h3>
                <div className="text-muted-foreground flex items-center gap-4 text-xs">
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm" style={{ background: 'var(--chart-2)' }} />
                        {year}
                    </span>
                    {hasPrev && (
                        <span className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-sm opacity-50" style={{ background: 'var(--muted-foreground)' }} />
                            {year - 1}
                        </span>
                    )}
                </div>
            </div>
            {/* Time flows start-to-end; keep the axis LTR even in the RTL app. */}
            <div dir="ltr" className="h-64 w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} margin={{ top: 4, right: 4, left: 4, bottom: 0 }} barCategoryGap="22%" barGap={2}>
                        <CartesianGrid vertical={false} stroke="var(--border)" />
                        <XAxis
                            dataKey="month"
                            tickLine={false}
                            axisLine={{ stroke: 'var(--border)' }}
                            tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                            interval={0}
                        />
                        <YAxis
                            width={44}
                            tickLine={false}
                            axisLine={false}
                            tickCount={4}
                            tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                            tickFormatter={(v: number) => (v >= 1000 ? `${Math.round(v / 100) / 10}${t('common.thousands_suffix')}` : String(v))}
                        />
                        <Tooltip
                            cursor={{ fill: 'var(--accent)', opacity: 0.7 }}
                            content={({ active, payload }) => {
                                if (!active || !payload?.length) return null;
                                const point = payload[0].payload as (typeof data)[number];
                                return (
                                    <div className="bg-popover text-popover-foreground rounded-lg border px-3 py-2 text-xs shadow-md" dir="rtl">
                                        <div className="font-medium">{monthName(point.month)}</div>
                                        <div className="mt-0.5 text-sm font-bold tabular-nums">
                                            {fmtAmount(point.current)} {t('common.currency')}
                                        </div>
                                        {hasPrev && (
                                            <div className="text-muted-foreground tabular-nums">
                                                {year - 1}: {fmtAmount(point.prev)}
                                            </div>
                                        )}
                                    </div>
                                );
                            }}
                        />
                        {hasPrev && <Bar dataKey="prev" fill="var(--muted-foreground)" fillOpacity={0.45} radius={[3, 3, 0, 0]} maxBarSize={16} />}
                        <Bar dataKey="current" fill="var(--chart-2)" radius={[3, 3, 0, 0]} maxBarSize={16} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

function SalesSection({ from, to, salesByProduct, salesByCustomer, salesByRoute }: ReportsPageProps) {
    const { t } = useTrans();

    return (
        <>
            <RangeBar from={from} to={to} />
            <div className="grid gap-4 xl:grid-cols-2">
                <NamedTable title={t('reports.sales_by_product')} nameLabel={t('orders.product')} rows={salesByProduct} showAvg />
                <NamedTable title={t('reports.by_route')} nameLabel={t('customers.route')} rows={salesByRoute} />
                <section className="rounded-xl border xl:col-span-2">
                    <h2 className="border-b p-3 font-semibold">{t('reports.by_customer')}</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('customers.name')}</TableHead>
                                <TableHead className="text-end">{t('reports.qty_loaves')}</TableHead>
                                <TableHead className="text-end">{t('common.total')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {salesByCustomer.map((row) => (
                                <TableRow key={row.code ?? row.name}>
                                    <TableCell>
                                        <span className="font-medium">{row.name}</span>
                                        {row.code && <span className="text-muted-foreground ms-2 text-xs tabular-nums">({row.code})</span>}
                                    </TableCell>
                                    <TableCell className="text-end tabular-nums">{fmtInt(row.qty)}</TableCell>
                                    <TableCell className="text-end font-semibold tabular-nums">{fmtAmount(row.total)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            </div>
        </>
    );
}

function NamedTable({ title, nameLabel, rows, showAvg }: { title: string; nameLabel: string; rows: NamedTotal[]; showAvg?: boolean }) {
    const { t } = useTrans();

    return (
        <section className="rounded-xl border">
            <h2 className="border-b p-3 font-semibold">{title}</h2>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{nameLabel}</TableHead>
                        <TableHead className="text-end">{t('reports.qty_loaves')}</TableHead>
                        <TableHead className="text-end">{t('common.total')}</TableHead>
                        {showAvg && <TableHead className="text-end">{t('reports.avg_price')}</TableHead>}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={row.name}>
                            <TableCell className="font-medium">{row.name}</TableCell>
                            <TableCell className="text-end tabular-nums">{fmtInt(row.qty)}</TableCell>
                            <TableCell className="text-end font-semibold tabular-nums">{fmtAmount(row.total)}</TableCell>
                            {showAvg && (
                                <TableCell className="text-muted-foreground text-end tabular-nums">
                                    {row.qty > 0 ? fmtPrice(parseFloat(row.total) / row.qty) : '—'}
                                </TableCell>
                            )}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </section>
    );
}

function CustomersSection({ from, to, inactiveDays, inactiveCustomers, dropPercent, volumeDrops }: ReportsPageProps) {
    const { t } = useTrans();

    const apply = (updates: Record<string, string | number>) =>
        router.get(
            route('reports.index'),
            { from, to, inactive_days: inactiveDays, drop_percent: dropPercent, ...updates },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <div className="grid gap-4 xl:grid-cols-2">
            <section className="rounded-xl border">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b p-3">
                    <h2 className="font-semibold">{t('reports.inactive')}</h2>
                    <label className="text-muted-foreground flex items-center gap-2 text-xs">
                        {t('reports.inactive_days')}
                        <Input
                            type="number"
                            min={1}
                            max={90}
                            dir="ltr"
                            className="h-8 w-16 text-center"
                            defaultValue={inactiveDays}
                            onBlur={(e) => apply({ inactive_days: e.target.value || 3 })}
                        />
                    </label>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('customers.name')}</TableHead>
                            <TableHead>{t('reports.last_order')}</TableHead>
                            <TableHead className="text-end">{t('delivery.balance')}</TableHead>
                            <TableHead>
                                <span className="sr-only">{t('common.actions')}</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {inactiveCustomers.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell>
                                    <div className="font-medium">{row.name}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {row.route}
                                        {row.phone && (
                                            <span className="ms-2 tabular-nums" dir="ltr">
                                                {row.phone}
                                            </span>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {row.last_invoice ? (
                                        <span className="tabular-nums" dir="ltr">
                                            {row.last_invoice}
                                        </span>
                                    ) : (
                                        <Badge variant="outline" className="text-muted-foreground text-[10px]">
                                            {t('reports.never')}
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell
                                    className={`text-end tabular-nums ${parseFloat(row.balance) > 0 ? 'font-semibold text-red-600 dark:text-red-400' : 'text-muted-foreground'}`}
                                >
                                    {fmtAmount(row.balance)}
                                </TableCell>
                                <TableCell>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={route('statements.show', row.id)}>{t('statements.open')}</Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </section>

            <section className="rounded-xl border">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b p-3">
                    <h2 className="font-semibold">{t('reports.drops')}</h2>
                    <label className="text-muted-foreground flex items-center gap-2 text-xs">
                        {t('reports.drop_percent')}
                        <Input
                            type="number"
                            min={5}
                            max={95}
                            dir="ltr"
                            className="h-8 w-16 text-center"
                            defaultValue={dropPercent}
                            onBlur={(e) => apply({ drop_percent: e.target.value || 30 })}
                        />
                    </label>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('customers.name')}</TableHead>
                            <TableHead className="text-end">{t('reports.prev_week')}</TableHead>
                            <TableHead className="text-end">{t('reports.this_week')}</TableHead>
                            <TableHead className="text-end">{t('reports.drop_percent')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {volumeDrops.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell className="font-medium">{row.name}</TableCell>
                                <TableCell className="text-end tabular-nums">{fmtInt(row.previous)}</TableCell>
                                <TableCell className="text-end tabular-nums">{fmtInt(row.current)}</TableCell>
                                <TableCell className="text-end font-bold text-red-600 tabular-nums dark:text-red-400">▼ {row.drop}%</TableCell>
                            </TableRow>
                        ))}
                        {volumeDrops.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={4} className="text-muted-foreground py-8 text-center">
                                    {t('common.no_results')}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </section>
        </div>
    );
}

function DriversSection({ from, to, collectionsByDriver, custodyByDriver }: ReportsPageProps) {
    const { t } = useTrans();

    return (
        <>
            <RangeBar from={from} to={to} />
            <div className="grid gap-4 xl:grid-cols-2">
                <section className="rounded-xl border">
                    <h2 className="border-b p-3 font-semibold">{t('reports.collections_by_driver')}</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('closes.driver')}</TableHead>
                                <TableHead className="text-end">{t('receipts.method.cash')}</TableHead>
                                <TableHead className="text-end">{t('common.total')}</TableHead>
                                <TableHead className="text-end">#</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {collectionsByDriver.map((row) => (
                                <TableRow key={row.name}>
                                    <TableCell className="font-medium">{row.name}</TableCell>
                                    <TableCell className="text-end tabular-nums">{fmtAmount(row.cash)}</TableCell>
                                    <TableCell className="text-end font-semibold tabular-nums">{fmtAmount(row.total)}</TableCell>
                                    <TableCell className="text-muted-foreground text-end tabular-nums">{row.count}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>

                <section className="rounded-xl border">
                    <h2 className="border-b p-3 font-semibold">{t('reports.custody')}</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('closes.driver')}</TableHead>
                                <TableHead className="text-end">{t('delivery.balance')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {custodyByDriver.map((row) => (
                                <TableRow key={row.name}>
                                    <TableCell className="font-medium">{row.name}</TableCell>
                                    <TableCell
                                        className={`text-end font-bold tabular-nums ${parseFloat(row.balance) > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'}`}
                                    >
                                        {fmtAmount(row.balance)}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {custodyByDriver.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={2} className="text-muted-foreground py-8 text-center">
                                        {t('common.no_results')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </section>
            </div>
        </>
    );
}
