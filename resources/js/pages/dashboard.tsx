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
    attention: AttentionItem[] | null;
}

export default function Dashboard({ money, stats, attention }: DashboardProps) {
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
