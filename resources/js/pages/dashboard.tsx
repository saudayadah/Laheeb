import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtInt } from '@/lib/format';
import { useCan, useTrans } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import {
    AlarmClock,
    BadgeDollarSign,
    CalendarDays,
    CalendarOff,
    Car,
    CheckCircle2,
    FileText,
    HandCoins,
    Package,
    PiggyBank,
    Scale,
    ShieldAlert,
    TrendingDown,
    TrendingUp,
    Truck,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface TrendPoint {
    date: string;
    label: string;
    total: number;
}

interface AttentionItem {
    key: string;
    count: number;
    amount?: string;
    href: string;
    tone: 'red' | 'amber' | 'blue';
}

interface MonthAnalysis {
    month_sales: string;
    delta_pct: number | null;
    month_collections: string;
    month_expenses: string;
    month_net: string;
    top_products: { name: string; qty: number; amount: string }[];
    top_customers: { name: string; amount: string }[];
}

interface DashboardProps {
    money: {
        trend: TrendPoint[];
        sales_today: string;
        cash_today: string;
        credit_today: string;
        mada_today: string;
        collections_today: string;
        receivables: string;
        custody: string;
        cash_box: string;
    } | null;
    stats: { customers: number; orders_today: number; invoices_today: number };
    analysis: MonthAnalysis | null;
    attention: AttentionItem[] | null;
}

export default function Dashboard({ money, stats, analysis, attention }: DashboardProps) {
    const { t } = useTrans();
    const can = useCan();

    return (
        <AppLayout breadcrumbs={[{ title: t('nav.dashboard'), href: '/dashboard' }]}>
            <Head title={t('dashboard.title')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <PageHeader title={t('dashboard.title')} />
                {money && (
                    <>
                        {/* The owner's headline numbers */}
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <BigCard label={t('dashboard.sales_today')} value={money.sales_today} highlight />
                            <BigCard label={t('dashboard.collections_today')} value={money.collections_today} />
                            <BigCard
                                label={t('dashboard.receivables')}
                                value={money.receivables}
                                warn={parseFloat(money.receivables) > 0}
                                link="/receivables"
                            />
                            <BigCard label={t('dashboard.custody')} value={money.custody} />
                        </div>

                        {attention !== null && <AttentionPanel items={attention} />}

                        {analysis !== null && <MonthAnalysisCard data={analysis} />}

                        {/* Today's sales split */}
                        <div>
                            <h2 className="text-muted-foreground mb-2 text-sm font-medium">{t('dashboard.sales_split')}</h2>
                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <SmallCard label={t('invoices.method.cash')} value={money.cash_today} />
                                <SmallCard label={t('invoices.method.credit')} value={money.credit_today} amber />
                                <SmallCard label={t('invoices.method.mada')} value={money.mada_today} />
                                <SmallCard label={t('dashboard.cash_box')} value={money.cash_box} />
                            </div>
                        </div>

                        <SalesTrend data={money.trend} />
                    </>
                )}

                {/* Quick actions */}
                <div>
                    <h2 className="text-muted-foreground mb-2 text-sm font-medium">{t('dashboard.shortcuts')}</h2>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {can('orders.manage') && (
                            <Shortcut
                                icon={CalendarDays}
                                label={t('nav.orders')}
                                href="/orders"
                                meta={`${fmtInt(stats.orders_today)} ${t('orders.customer')}`}
                            />
                        )}
                        {can('invoices.manage') && (
                            <Shortcut
                                icon={FileText}
                                label={t('nav.invoices')}
                                href="/invoices"
                                meta={`${fmtInt(stats.invoices_today)} ${t('invoices.count')}`}
                            />
                        )}
                        {can('receipts.manage') && <Shortcut icon={HandCoins} label={t('nav.receipts')} href="/receipts" />}
                        {can('balances.view') && <Shortcut icon={Scale} label={t('nav.receivables')} href="/receivables" />}
                        {can('deliveries.own') && !can('invoices.manage') && <Shortcut icon={Truck} label={t('nav.delivery')} href="/delivery" />}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

const ATTENTION_ICONS: Record<string, LucideIcon> = {
    overdue_invoices: AlarmClock,
    over_limit: ShieldAlert,
    pending_expenses: Wallet,
    pending_advances: PiggyBank,
    unpaid_payroll: BadgeDollarSign,
    low_materials: Package,
    vehicles_due: Car,
    overdue_leaves: CalendarOff,
    unconfirmed_orders: CalendarDays,
};

const ATTENTION_TONES: Record<AttentionItem['tone'], string> = {
    red: 'text-red-600 dark:text-red-400',
    amber: 'text-amber-700 dark:text-amber-400',
    blue: 'text-sky-700 dark:text-sky-400',
};

/** The owner's morning checklist: everything waiting for a decision, each row a link. */
function AttentionPanel({ items }: { items: AttentionItem[] }) {
    const { t } = useTrans();

    return (
        <div className="rounded-xl border">
            <h2 className="border-b px-4 py-3 text-sm font-semibold">{t('dashboard.attention')}</h2>
            {items.length === 0 ? (
                <div className="flex items-center gap-2 px-4 py-4 text-sm text-emerald-700 dark:text-emerald-400">
                    <CheckCircle2 className="size-4 shrink-0" />
                    {t('dashboard.attention_clear')}
                </div>
            ) : (
                <ul className="divide-y">
                    {items.map((item) => {
                        const Icon = ATTENTION_ICONS[item.key] ?? AlarmClock;
                        return (
                            <li key={item.key}>
                                <Link href={item.href} className="hover:bg-accent flex items-center gap-3 px-4 py-2.5 transition-colors">
                                    <Icon className={`size-4 shrink-0 ${ATTENTION_TONES[item.tone]}`} />
                                    <span className="flex-1 text-sm">{t(`dashboard.attn.${item.key}`)}</span>
                                    {item.amount && (
                                        <span className={`text-sm font-semibold tabular-nums ${ATTENTION_TONES[item.tone]}`}>
                                            {fmtAmount(item.amount)}
                                        </span>
                                    )}
                                    <span className="bg-muted text-foreground min-w-7 rounded-full px-2 py-0.5 text-center text-xs font-bold tabular-nums">
                                        {fmtInt(item.count)}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}

/**
 * The month in one card: four plain numbers (with a same-days-last-month
 * compare on sales) and the top-3 products and customers, linking to the
 * full reports page for anything deeper.
 */
function MonthAnalysisCard({ data }: { data: MonthAnalysis }) {
    const { t } = useTrans();
    const net = parseFloat(data.month_net);
    const delta = data.delta_pct;

    return (
        <div className="rounded-xl border">
            <div className="flex items-center justify-between border-b px-4 py-3">
                <h2 className="text-sm font-semibold">{t('dashboard.analysis')}</h2>
                <Link href="/reports" className="text-primary text-xs font-medium hover:underline">
                    {t('dashboard.full_report')}
                </Link>
            </div>

            <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div className="text-2xl font-bold tabular-nums">{fmtAmount(data.month_sales)}</div>
                    <div className="text-muted-foreground mt-0.5 text-xs">
                        {t('dashboard.month_sales')} ({t('common.currency')})
                    </div>
                    {delta !== null && (
                        <div
                            className={`mt-1 flex items-center gap-1 text-xs font-medium ${
                                delta >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'
                            }`}
                        >
                            {delta >= 0 ? <TrendingUp className="size-3.5 shrink-0" /> : <TrendingDown className="size-3.5 shrink-0" />}
                            <span className="tabular-nums">{fmtInt(Math.abs(delta))}%</span>
                            <span className="text-muted-foreground font-normal">{t('dashboard.vs_prev')}</span>
                        </div>
                    )}
                </div>
                <div>
                    <div className="text-2xl font-bold tabular-nums">{fmtAmount(data.month_collections)}</div>
                    <div className="text-muted-foreground mt-0.5 text-xs">
                        {t('dashboard.month_collections')} ({t('common.currency')})
                    </div>
                </div>
                <div>
                    <div className="text-2xl font-bold tabular-nums">{fmtAmount(data.month_expenses)}</div>
                    <div className="text-muted-foreground mt-0.5 text-xs">
                        {t('dashboard.month_expenses')} ({t('common.currency')})
                    </div>
                </div>
                <div>
                    <div
                        className={`text-2xl font-bold tabular-nums ${net >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}
                    >
                        {fmtAmount(data.month_net)}
                    </div>
                    <div className="text-muted-foreground mt-0.5 text-xs">
                        {t('dashboard.month_net')} ({t('common.currency')})
                    </div>
                </div>
            </div>

            {(data.top_products.length > 0 || data.top_customers.length > 0) && (
                <div className="grid gap-4 border-t p-4 sm:grid-cols-2">
                    <TopList
                        title={t('dashboard.top_products')}
                        rows={data.top_products.map((p) => ({
                            name: p.name,
                            meta: `${fmtInt(p.qty)} ${t('dashboard.qty_units')}`,
                            amount: p.amount,
                        }))}
                    />
                    <TopList title={t('dashboard.top_customers')} rows={data.top_customers.map((c) => ({ name: c.name, amount: c.amount }))} />
                </div>
            )}
        </div>
    );
}

function TopList({ title, rows }: { title: string; rows: { name: string; meta?: string; amount: string }[] }) {
    const { t } = useTrans();
    if (rows.length === 0) return null;

    return (
        <div>
            <h3 className="text-muted-foreground mb-2 text-xs font-medium">{title}</h3>
            <ol className="flex flex-col gap-1.5">
                {rows.map((row, i) => (
                    <li key={row.name} className="flex items-center gap-2 text-sm">
                        <span className="bg-muted text-muted-foreground flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold tabular-nums">
                            {fmtInt(i + 1)}
                        </span>
                        <span className="min-w-0 flex-1 truncate font-medium">{row.name}</span>
                        {row.meta && <span className="text-muted-foreground shrink-0 text-xs tabular-nums">{row.meta}</span>}
                        <span className="shrink-0 text-sm font-semibold tabular-nums">
                            {fmtAmount(row.amount)} <span className="text-muted-foreground text-xs font-normal">{t('common.currency')}</span>
                        </span>
                    </li>
                ))}
            </ol>
        </div>
    );
}

/** Single-series sales trend: title names the series, hover carries the exact value. */
function SalesTrend({ data }: { data: TrendPoint[] }) {
    const { t } = useTrans();

    if (!data?.length || data.every((p) => p.total === 0)) return null;

    return (
        <div className="rounded-xl border p-4">
            <h2 className="mb-3 text-sm font-semibold">{t('dashboard.trend')}</h2>
            {/* Time flows start-to-end; keep the axis LTR even in the RTL app. */}
            <div dir="ltr" className="h-56 w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} margin={{ top: 4, right: 4, left: 4, bottom: 0 }} barCategoryGap="28%">
                        <CartesianGrid vertical={false} stroke="var(--border)" />
                        <XAxis
                            dataKey="label"
                            tickLine={false}
                            axisLine={{ stroke: 'var(--border)' }}
                            tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                            interval="preserveStartEnd"
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
                                const point = payload[0].payload as TrendPoint;
                                return (
                                    <div className="bg-popover text-popover-foreground rounded-lg border px-3 py-2 text-xs shadow-md" dir="rtl">
                                        <div className="text-muted-foreground tabular-nums" dir="ltr">
                                            {point.date}
                                        </div>
                                        <div className="mt-0.5 text-sm font-bold tabular-nums">
                                            {fmtAmount(point.total)} {t('common.currency')}
                                        </div>
                                    </div>
                                );
                            }}
                        />
                        <Bar dataKey="total" fill="var(--chart-2)" radius={[4, 4, 0, 0]} maxBarSize={34} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

function BigCard({ label, value, highlight, warn, link }: { label: string; value: string; highlight?: boolean; warn?: boolean; link?: string }) {
    const { t } = useTrans();
    const inner = (
        <div
            className={`h-full rounded-xl border p-4 ${highlight ? 'border-primary/30 bg-primary/10 dark:bg-primary/15' : ''} ${link ? 'hover:bg-accent transition-colors' : ''}`}
        >
            <div className={`text-3xl font-bold tabular-nums ${warn ? 'text-red-600 dark:text-red-400' : ''}`}>{fmtAmount(value)}</div>
            <div className="text-muted-foreground mt-1 text-sm">
                {label} ({t('common.currency')})
            </div>
        </div>
    );

    return link ? <Link href={link}>{inner}</Link> : inner;
}

function SmallCard({ label, value, amber }: { label: string; value: string; amber?: boolean }) {
    return (
        <div className="rounded-xl border p-3">
            <div className={`text-xl font-semibold tabular-nums ${amber && parseFloat(value) > 0 ? 'text-amber-700 dark:text-amber-400' : ''}`}>
                {fmtAmount(value)}
            </div>
            <div className="text-muted-foreground mt-0.5 text-xs">{label}</div>
        </div>
    );
}

function Shortcut({ icon: Icon, label, href, meta }: { icon: typeof CalendarDays; label: string; href: string; meta?: string }) {
    return (
        <Link href={href} className="hover:bg-accent flex items-center gap-3 rounded-xl border p-3 transition-colors">
            <div className="bg-muted flex size-10 items-center justify-center rounded-lg">
                <Icon className="text-muted-foreground size-5" />
            </div>
            <div className="min-w-0">
                <div className="truncate text-sm font-medium">{label}</div>
                {meta && <div className="text-muted-foreground text-xs">{meta}</div>}
            </div>
        </Link>
    );
}
