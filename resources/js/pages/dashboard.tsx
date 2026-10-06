import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtInt } from '@/lib/format';
import { useCan, useTrans } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { CalendarDays, FileText, HandCoins, Scale, Truck } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface TrendPoint {
    date: string;
    label: string;
    total: number;
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
}

export default function Dashboard({ money, stats }: DashboardProps) {
    const { t } = useTrans();
    const can = useCan();

    return (
        <AppLayout breadcrumbs={[{ title: t('nav.dashboard'), href: '/dashboard' }]}>
            <Head title={t('dashboard.title')} />
            <div className="flex h-full flex-1 flex-col gap-5 p-4">
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
                            tickFormatter={(v: number) => (v >= 1000 ? `${Math.round(v / 100) / 10}k` : String(v))}
                        />
                        <Tooltip
                            cursor={{ fill: 'var(--accent)', opacity: 0.4 }}
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
        <div className={`h-full rounded-xl border p-4 ${highlight ? 'bg-accent/40' : ''} ${link ? 'hover:bg-accent/30 transition-colors' : ''}`}>
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
            <div className={`text-xl font-semibold tabular-nums ${amber && parseFloat(value) > 0 ? 'text-amber-600 dark:text-amber-400' : ''}`}>
                {fmtAmount(value)}
            </div>
            <div className="text-muted-foreground mt-0.5 text-xs">{label}</div>
        </div>
    );
}

function Shortcut({ icon: Icon, label, href, meta }: { icon: typeof CalendarDays; label: string; href: string; meta?: string }) {
    return (
        <Link href={href} className="hover:bg-accent/40 flex items-center gap-3 rounded-xl border p-3 transition-colors">
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
