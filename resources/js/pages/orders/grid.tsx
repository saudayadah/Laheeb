import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { fmtInt, fmtPrice } from '@/lib/format';
import { postJson } from '@/lib/http';
import { useCan, useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, CheckCheck, ChevronLeft, ChevronRight, Copy, History, Printer, Repeat, Search } from 'lucide-react';
import { memo, useCallback, useMemo, useRef, useState } from 'react';

interface GridProduct {
    id: number;
    name_ar: string;
    name_en: string | null;
    size_cm: number | null;
}

interface GridCustomer {
    id: number;
    code: string;
    name: string;
    payment_term: string;
    price: string | null;
}

interface RouteGroup {
    route: { id: number; name: string } | null;
    customers: GridCustomer[];
}

interface GridProps {
    date: string;
    weekday: number;
    products: GridProduct[];
    routeGroups: RouteGroup[];
    cells: Record<string, number>;
    confirmedCustomerIds: number[];
    canViewPrices: boolean;
}

type CellStatus = 'idle' | 'saving' | 'saved' | 'error';

const keyOf = (customerId: number, productId: number) => `${customerId}:${productId}`;

export default function OrdersGrid({ date, weekday, products, routeGroups, cells, confirmedCustomerIds, canViewPrices }: GridProps) {
    const { t, locale } = useTrans();
    const can = useCan();

    // Mutable store of all quantities; cells manage their own inputs,
    // this ref + version counter feed the totals.
    const valuesRef = useRef<Record<string, string>>(Object.fromEntries(Object.entries(cells).map(([k, v]) => [k, String(v)])));
    const [version, setVersion] = useState(0);
    const [resetCounter, setResetCounter] = useState(0);
    const [query, setQuery] = useState('');
    const [pasteError, setPasteError] = useState(false);

    const inputRefs = useRef<Map<string, HTMLInputElement>>(new Map());
    const confirmed = useMemo(() => new Set(confirmedCustomerIds), [confirmedCustomerIds]);

    const allCustomers = useMemo(() => routeGroups.flatMap((g) => g.customers), [routeGroups]);

    // Quick client-side customer filter; quantities and totals always cover the whole day.
    const visibleGroups = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (q === '') return routeGroups;

        return routeGroups
            .map((g) => ({ ...g, customers: g.customers.filter((c) => c.name.toLowerCase().includes(q) || c.code.toLowerCase().includes(q)) }))
            .filter((g) => g.customers.length > 0);
    }, [routeGroups, query]);

    // Navigation and paste operate on what the user actually SEES, so a
    // multi-row paste while filtering never lands on hidden rows.
    const visibleCustomers = useMemo(() => visibleGroups.flatMap((g) => g.customers), [visibleGroups]);

    const keyToPos = useMemo(() => {
        const map = new Map<string, [number, number]>();
        visibleCustomers.forEach((c, r) => products.forEach((p, col) => map.set(keyOf(c.id, p.id), [r, col])));
        return map;
    }, [visibleCustomers, products]);

    // Memoized cells keep their first callbacks; these refs keep those
    // callbacks pointed at the CURRENT visible rows after every filter change.
    const visibleCustomersRef = useRef(visibleCustomers);
    visibleCustomersRef.current = visibleCustomers;
    const keyToPosRef = useRef(keyToPos);
    keyToPosRef.current = keyToPos;

    const bump = useCallback(() => setVersion((v) => v + 1), []);

    const saveCell = useCallback(
        async (cellKey: string, qty: number) => {
            const [customerId, productId] = cellKey.split(':').map(Number);
            await postJson(route('orders.cells'), {
                date,
                cells: [{ customer_id: customerId, product_id: productId, qty }],
            });
        },
        [date],
    );

    const focusCell = useCallback(
        (row: number, col: number) => {
            const customers = visibleCustomersRef.current;
            if (row < 0 || row >= customers.length || col < 0 || col >= products.length) return;
            const key = keyOf(customers[row].id, products[col].id);
            const input = inputRefs.current.get(key);
            if (input) {
                input.focus();
                input.select();
            }
        },
        [products],
    );

    const navigate = useCallback(
        (cellKey: string, dRow: number, dCol: number) => {
            const pos = keyToPosRef.current.get(cellKey);
            if (!pos) return;
            // In RTL the visual left/right arrows are mirrored.
            const flip = locale === 'ar' ? -1 : 1;
            focusCell(pos[0] + dRow, pos[1] + dCol * flip);
        },
        [focusCell, locale],
    );

    const pasteBlock = useCallback(
        async (cellKey: string, text: string) => {
            const pos = keyToPosRef.current.get(cellKey);
            if (!pos) return;

            const customers = visibleCustomersRef.current;
            const rows = text
                .replace(/\r/g, '')
                .split('\n')
                .filter((line, i, arr) => !(i === arr.length - 1 && line === ''));
            const updates: { customer_id: number; product_id: number; qty: number }[] = [];

            rows.forEach((line, dr) => {
                line.split('\t').forEach((raw, dc) => {
                    const r = pos[0] + dr;
                    const c = pos[1] + dc;
                    if (r >= customers.length || c >= products.length) return;
                    const customer = customers[r];
                    if (confirmed.has(customer.id)) return;

                    // Accept Arabic-Indic digits; a token with letters but no
                    // digit at all is skipped instead of silently clearing.
                    const normalized = raw
                        .trim()
                        .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
                        .replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));
                    if (normalized !== '' && !/\d/.test(normalized)) return;

                    const qty = parseInt(normalized.replace(/[^\d]/g, ''), 10);
                    const value = Number.isNaN(qty) || qty <= 0 ? 0 : qty;
                    updates.push({ customer_id: customer.id, product_id: products[c].id, qty: value });
                });
            });

            if (updates.length === 0) return;

            // Save first, then show: a failed paste must not pretend to be saved.
            try {
                for (let i = 0; i < updates.length; i += 400) {
                    await postJson(route('orders.cells'), { date, cells: updates.slice(i, i + 400) });
                }
            } catch {
                setPasteError(true);
                return;
            }

            setPasteError(false);
            for (const u of updates) {
                valuesRef.current[keyOf(u.customer_id, u.product_id)] = u.qty > 0 ? String(u.qty) : '';
            }
            setResetCounter((n) => n + 1);
            bump();
        },
        [products, confirmed, date, bump],
    );

    const registerRef = useCallback((key: string, el: HTMLInputElement | null) => {
        if (el) inputRefs.current.set(key, el);
        else inputRefs.current.delete(key);
    }, []);

    // Totals (recomputed on every committed change — cheap integer math).
    const totals = useMemo(() => {
        void version;
        const byCustomer: Record<number, number> = {};
        const byProduct: Record<number, number> = {};
        let grand = 0;
        for (const [key, value] of Object.entries(valuesRef.current)) {
            const qty = parseInt(value, 10);
            if (!qty || qty <= 0) continue;
            const [customerId, productId] = key.split(':').map(Number);
            byCustomer[customerId] = (byCustomer[customerId] ?? 0) + qty;
            byProduct[productId] = (byProduct[productId] ?? 0) + qty;
            grand += qty;
        }
        return { byCustomer, byProduct, grand };
    }, [version]);

    const customersWithOrders = useMemo(() => allCustomers.filter((c) => (totals.byCustomer[c.id] ?? 0) > 0).length, [allCustomers, totals]);

    const changeDate = (newDate: string) => {
        if (newDate) router.get(route('orders.grid'), { date: newDate }, { preserveState: false });
    };

    const shiftDate = (days: number) => {
        const d = new Date(date + 'T00:00:00');
        d.setDate(d.getDate() + days);
        changeDate(d.toISOString().slice(0, 10));
    };

    const fill = (source: string) => {
        router.post(route('orders.fill'), { date, source }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('orders.title'), href: '/orders' }]}>
            <Head title={t('orders.title')} />
            <div className="flex h-full flex-col gap-3 p-4">
                {/* Summary strip: the big simple numbers first */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="icon" className="size-9" aria-label={t('orders.prev_day')} onClick={() => shiftDate(-1)}>
                            {locale === 'ar' ? <ChevronRight className="size-4" /> : <ChevronLeft className="size-4" />}
                        </Button>
                        <Input type="date" value={date} onChange={(e) => changeDate(e.target.value)} className="w-40" dir="ltr" />
                        <Button variant="outline" size="icon" className="size-9" aria-label={t('orders.next_day')} onClick={() => shiftDate(1)}>
                            {locale === 'ar' ? <ChevronLeft className="size-4" /> : <ChevronRight className="size-4" />}
                        </Button>
                        <Badge variant="secondary" className="gap-1 text-sm">
                            <CalendarDays className="size-3.5" />
                            {t(`weekday.${weekday}`)}
                        </Badge>
                    </div>
                    <div className="flex items-center gap-6 text-sm">
                        <div className="text-center">
                            <div className="text-2xl font-semibold tabular-nums">{fmtInt(totals.grand)}</div>
                            <div className="text-muted-foreground text-xs">
                                {t('orders.grand_total')} ({t('orders.loaves')})
                            </div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-semibold tabular-nums">{fmtInt(customersWithOrders)}</div>
                            <div className="text-muted-foreground text-xs">{t('orders.customer')}</div>
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" onClick={() => fill('yesterday')}>
                        <Copy className="size-4" /> {t('orders.fill_yesterday')}
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => fill('last_week')}>
                        <History className="size-4" /> {t('orders.fill_last_week')}
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => fill('standing')}>
                        <Repeat className="size-4" /> {t('orders.apply_standing')}
                    </Button>
                    <span className="flex-1" />
                    <div className="relative">
                        <Search className="text-muted-foreground absolute start-2.5 top-1/2 size-4 -translate-y-1/2" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={t('orders.search_customer')}
                            aria-label={t('orders.search_customer')}
                            className="h-9 w-44 ps-8"
                        />
                    </div>
                    {can('invoices.manage') && (
                        <Button size="sm" onClick={() => router.post(route('invoices.confirm-day'), { date }, { preserveScroll: true })}>
                            <CheckCheck className="size-4" /> {t('invoices.confirm_day')}
                        </Button>
                    )}
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('orders.production', { date })}>
                            <Printer className="size-4" /> {t('orders.production_sheet')}
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('orders.loading', { date })}>
                            <Printer className="size-4" /> {t('orders.loading_sheet')}
                        </Link>
                    </Button>
                </div>

                {pasteError && (
                    <div className="rounded-lg border border-red-300 bg-red-100/60 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/20 dark:text-red-400">
                        {t('orders.paste_failed')}
                    </div>
                )}

                <p className="text-muted-foreground text-xs">{t('orders.hint')}</p>

                {/* The grid */}
                <div className="relative flex-1 overflow-auto rounded-xl border" style={{ maxHeight: 'calc(100vh - 275px)' }}>
                    <table className="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th className="bg-muted sticky start-0 top-0 z-30 min-w-52 border-e border-b px-3 py-2 text-start font-medium">
                                    {t('orders.customer')}
                                </th>
                                {products.map((p) => (
                                    <th
                                        key={p.id}
                                        className="bg-muted sticky top-0 z-20 min-w-16 border-b px-1 py-2 text-center font-medium whitespace-nowrap"
                                    >
                                        {p.name_ar}
                                    </th>
                                ))}
                                <th className="bg-muted sticky top-0 z-20 min-w-16 border-s border-b px-2 py-2 text-center font-semibold">
                                    {t('orders.row_total')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibleGroups.length === 0 && (
                                <tr>
                                    <td colSpan={products.length + 2} className="text-muted-foreground px-3 py-8 text-center">
                                        {t('orders.search_empty')}
                                    </td>
                                </tr>
                            )}
                            {visibleGroups.map((group) => (
                                <GroupRows
                                    key={group.route?.id ?? 'none'}
                                    group={group}
                                    products={products}
                                    confirmed={confirmed}
                                    valuesRef={valuesRef}
                                    resetCounter={resetCounter}
                                    totals={totals}
                                    routeTotal={group.customers.reduce((sum, c) => sum + (totals.byCustomer[c.id] ?? 0), 0)}
                                    canViewPrices={canViewPrices}
                                    saveCell={saveCell}
                                    navigate={navigate}
                                    pasteBlock={pasteBlock}
                                    registerRef={registerRef}
                                    bump={bump}
                                />
                            ))}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td className="bg-muted sticky start-0 bottom-0 z-30 border-e border-t px-3 py-2 font-semibold">
                                    {t('orders.grand_total')}
                                </td>
                                {products.map((p) => (
                                    <td
                                        key={p.id}
                                        className="bg-muted sticky bottom-0 z-20 border-t px-1 py-2 text-center font-semibold tabular-nums"
                                    >
                                        {totals.byProduct[p.id] ? fmtInt(totals.byProduct[p.id]) : ''}
                                    </td>
                                ))}
                                <td className="bg-muted sticky bottom-0 z-20 border-s border-t px-2 py-2 text-center font-bold tabular-nums">
                                    {fmtInt(totals.grand)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

interface GroupRowsProps {
    group: RouteGroup;
    products: GridProduct[];
    confirmed: Set<number>;
    valuesRef: React.MutableRefObject<Record<string, string>>;
    resetCounter: number;
    totals: { byCustomer: Record<number, number> };
    routeTotal: number;
    canViewPrices: boolean;
    saveCell: (key: string, qty: number) => Promise<void>;
    navigate: (key: string, dRow: number, dCol: number) => void;
    pasteBlock: (key: string, text: string) => void;
    registerRef: (key: string, el: HTMLInputElement | null) => void;
    bump: () => void;
}

function GroupRows({
    group,
    products,
    confirmed,
    valuesRef,
    resetCounter,
    totals,
    routeTotal,
    canViewPrices,
    saveCell,
    navigate,
    pasteBlock,
    registerRef,
    bump,
}: GroupRowsProps) {
    const { t } = useTrans();

    return (
        <>
            <tr>
                <td
                    colSpan={products.length + 2}
                    className="bg-accent text-accent-foreground sticky start-0 border-y px-3 py-1.5 text-xs font-semibold"
                >
                    {group.route?.name ?? t('orders.no_route')}
                    <span className="text-muted-foreground ms-3 font-normal">
                        {t('orders.route_total')}: <span className="tabular-nums">{fmtInt(routeTotal)}</span>
                    </span>
                </td>
            </tr>
            {group.customers.map((customer) => {
                const isConfirmed = confirmed.has(customer.id);
                return (
                    <tr key={customer.id} className="group">
                        <td className="bg-background group-hover:bg-muted/50 sticky start-0 z-10 border-e border-b px-3 py-1">
                            <div className="flex items-center justify-between gap-2">
                                <div className="min-w-0">
                                    <div className="truncate font-medium">{customer.name}</div>
                                    <div className="text-muted-foreground text-xs tabular-nums">
                                        {customer.code}
                                        {canViewPrices && customer.price && <span className="ms-2">{fmtPrice(customer.price)}</span>}
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    {customer.payment_term === 'credit' && (
                                        <span className="rounded bg-amber-100 px-1 text-[10px] text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            {t('customers.term.credit')}
                                        </span>
                                    )}
                                    {isConfirmed && (
                                        <span className="rounded bg-emerald-100 px-1 text-[10px] text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            {t('orders.confirmed_badge')}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </td>
                        {products.map((product) => {
                            const key = keyOf(customer.id, product.id);
                            return (
                                <td key={product.id} className="border-b p-0">
                                    <GridCell
                                        key={`${key}:${resetCounter}`}
                                        cellKey={key}
                                        resetCounter={resetCounter}
                                        initialValue={valuesRef.current[key] ?? ''}
                                        disabled={isConfirmed}
                                        ariaLabel={`${customer.name} - ${product.name_ar}`}
                                        valuesRef={valuesRef}
                                        saveCell={saveCell}
                                        navigate={navigate}
                                        pasteBlock={pasteBlock}
                                        registerRef={registerRef}
                                        bump={bump}
                                    />
                                </td>
                            );
                        })}
                        <td className="text-muted-foreground border-s border-b px-2 py-1 text-center font-medium tabular-nums">
                            {totals.byCustomer[customer.id] ? fmtInt(totals.byCustomer[customer.id]) : ''}
                        </td>
                    </tr>
                );
            })}
        </>
    );
}

interface GridCellProps {
    cellKey: string;
    resetCounter: number;
    initialValue: string;
    disabled: boolean;
    ariaLabel: string;
    valuesRef: React.MutableRefObject<Record<string, string>>;
    saveCell: (key: string, qty: number) => Promise<void>;
    navigate: (key: string, dRow: number, dCol: number) => void;
    pasteBlock: (key: string, text: string) => void;
    registerRef: (key: string, el: HTMLInputElement | null) => void;
    bump: () => void;
}

const GridCell = memo(
    function GridCell({ cellKey, initialValue, disabled, ariaLabel, valuesRef, saveCell, navigate, pasteBlock, registerRef, bump }: GridCellProps) {
        const [value, setValue] = useState(initialValue);
        const [status, setStatus] = useState<CellStatus>('idle');
        const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
        const lastSaved = useRef(initialValue);

        const commit = (raw: string) => {
            const cleaned = raw.replace(/[^\d]/g, '');
            setValue(cleaned);
            valuesRef.current[cellKey] = cleaned;
            bump();

            if (timer.current) clearTimeout(timer.current);
            timer.current = setTimeout(() => void persist(cleaned), 500);
        };

        const persist = async (cleaned: string) => {
            if (cleaned === lastSaved.current) return;
            setStatus('saving');
            try {
                await saveCell(cellKey, cleaned === '' ? 0 : parseInt(cleaned, 10));
                lastSaved.current = cleaned;
                setStatus('saved');
                setTimeout(() => setStatus((s) => (s === 'saved' ? 'idle' : s)), 1500);
            } catch {
                setStatus('error');
            }
        };

        const flush = () => {
            if (timer.current) {
                clearTimeout(timer.current);
                timer.current = null;
                void persist(value);
            }
        };

        const onKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
            const nav = (dRow: number, dCol: number) => {
                e.preventDefault();
                flush();
                navigate(cellKey, dRow, dCol);
            };
            if (e.key === 'ArrowDown') nav(1, 0);
            else if (e.key === 'ArrowUp') nav(-1, 0);
            else if (e.key === 'ArrowRight') nav(0, 1);
            else if (e.key === 'ArrowLeft') nav(0, -1);
            else if (e.key === 'Enter') nav(e.shiftKey ? -1 : 1, 0);
        };

        const borderColor =
            status === 'error'
                ? 'ring-2 ring-red-500'
                : status === 'saving'
                  ? 'ring-1 ring-amber-500'
                  : status === 'saved'
                    ? 'ring-1 ring-emerald-500'
                    : '';

        return (
            <input
                ref={(el) => registerRef(cellKey, el)}
                type="text"
                inputMode="numeric"
                dir="ltr"
                aria-label={ariaLabel}
                disabled={disabled}
                value={value}
                onChange={(e) => commit(e.target.value)}
                onKeyDown={onKeyDown}
                onFocus={(e) => e.target.select()}
                onBlur={flush}
                onPaste={(e) => {
                    const text = e.clipboardData.getData('text');
                    if (text.includes('\t') || text.includes('\n')) {
                        e.preventDefault();
                        pasteBlock(cellKey, text);
                    }
                }}
                className={`focus:bg-accent h-8 w-full min-w-14 border-0 bg-transparent px-1 text-center tabular-nums outline-none disabled:opacity-40 ${borderColor}`}
            />
        );
    },
    (prev, next) => prev.resetCounter === next.resetCounter && prev.disabled === next.disabled && prev.cellKey === next.cellKey,
);
