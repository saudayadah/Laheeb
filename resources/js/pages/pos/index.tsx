import { Field, NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtPrice } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { Banknote, CreditCard, LoaderCircle, Minus, Plus, Trash2 } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

interface PosProduct {
    id: number;
    name_ar: string;
    price: string;
}

interface PosProps {
    products: PosProduct[];
    todayCash: string;
    todayMada: string;
    todayCount: number;
}

export default function PosIndex({ products, todayCash, todayMada, todayCount }: PosProps) {
    const { t } = useTrans();
    const [cart, setCart] = useState<Record<number, number>>({});
    const [submitting, setSubmitting] = useState(false);
    const idempotencyKey = useRef(crypto.randomUUID());

    const total = useMemo(() => products.reduce((sum, p) => sum + (cart[p.id] ?? 0) * parseFloat(p.price), 0), [products, cart]);

    const add = (id: number, delta: number) =>
        setCart((c) => {
            const next = Math.max(0, (c[id] ?? 0) + delta);
            const copy = { ...c };
            if (next === 0) delete copy[id];
            else copy[id] = next;
            return copy;
        });

    const checkout = (method: 'cash' | 'mada') => {
        setSubmitting(true);
        router.post(
            route('pos.sale'),
            {
                items: Object.entries(cart).map(([productId, qty]) => ({ product_id: Number(productId), qty })),
                payment_method: method,
                idempotency_key: idempotencyKey.current,
            },
            {
                onSuccess: () => {
                    setCart({});
                    idempotencyKey.current = crypto.randomUUID();
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const inCart = products.filter((p) => (cart[p.id] ?? 0) > 0);

    return (
        <AppLayout breadcrumbs={[{ title: t('pos.title'), href: '/pos' }]}>
            <Head title={t('pos.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={t('pos.title')} />

                {/* Today's simple numbers */}
                <div className="grid grid-cols-3 gap-3">
                    <div className="rounded-xl border p-3 text-center">
                        <div className="text-xl font-bold tabular-nums">{fmtAmount(todayCash)}</div>
                        <div className="text-muted-foreground text-xs">{t('pos.today_cash')}</div>
                    </div>
                    <div className="rounded-xl border p-3 text-center">
                        <div className="text-xl font-bold tabular-nums">{fmtAmount(todayMada)}</div>
                        <div className="text-muted-foreground text-xs">{t('pos.today_mada')}</div>
                    </div>
                    <div className="rounded-xl border p-3 text-center">
                        <div className="text-xl font-bold tabular-nums">{todayCount}</div>
                        <div className="text-muted-foreground text-xs">{t('pos.sales_count')}</div>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1fr_20rem]">
                    {/* Product buttons */}
                    <div className="grid auto-rows-min grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                        {products.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                onClick={() => add(p.id, 1)}
                                className="bg-card hover:bg-accent flex h-20 flex-col items-center justify-center gap-1 rounded-xl border text-sm font-medium active:scale-[0.98]"
                            >
                                <span>{p.name_ar}</span>
                                <span className="text-muted-foreground text-xs tabular-nums">{fmtPrice(p.price)}</span>
                                {(cart[p.id] ?? 0) > 0 && (
                                    <span className="bg-primary text-primary-foreground rounded-full px-2 text-xs font-bold tabular-nums">
                                        {cart[p.id]}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>

                    {/* Cart */}
                    <div className="flex flex-col gap-3">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-base">{t('pos.cart')}</CardTitle>
                                {inCart.length > 0 && (
                                    <Button variant="ghost" size="sm" onClick={() => setCart({})}>
                                        <Trash2 className="size-4" /> {t('pos.clear')}
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {inCart.length === 0 && <p className="text-muted-foreground text-sm">{t('common.no_results')}</p>}
                                {inCart.map((p) => (
                                    <div key={p.id} className="flex items-center gap-2">
                                        <span className="min-w-0 flex-1 truncate text-sm">{p.name_ar}</span>
                                        <Button variant="outline" size="icon" className="size-7" onClick={() => add(p.id, -1)}>
                                            <Minus className="size-3" />
                                        </Button>
                                        <span className="w-8 text-center font-semibold tabular-nums">{cart[p.id]}</span>
                                        <Button variant="outline" size="icon" className="size-7" onClick={() => add(p.id, 1)}>
                                            <Plus className="size-3" />
                                        </Button>
                                    </div>
                                ))}

                                <div className="mt-2 flex items-center justify-between border-t pt-3">
                                    <span className="text-muted-foreground text-sm">{t('invoices.total')}</span>
                                    <span className="text-2xl font-bold tabular-nums">{fmtAmount(total)}</span>
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <Button className="h-12" disabled={total <= 0 || submitting} onClick={() => checkout('cash')}>
                                        {submitting ? <LoaderCircle className="size-4 animate-spin" /> : <Banknote className="size-4" />}
                                        {t('pos.checkout_cash')}
                                    </Button>
                                    <Button variant="secondary" className="h-12" disabled={total <= 0 || submitting} onClick={() => checkout('mada')}>
                                        <CreditCard className="size-4" />
                                        {t('pos.checkout_mada')}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <DailyRetailCard />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function DailyRetailCard() {
    const { t } = useTrans();
    const [amount, setAmount] = useState('');
    const [method, setMethod] = useState('cash');
    const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
    const [saving, setSaving] = useState(false);

    const submit = () => {
        if (!amount) return;
        setSaving(true);
        router.post(
            route('pos.daily-retail'),
            { amount, payment_method: method, date, idempotency_key: crypto.randomUUID() },
            {
                onSuccess: () => setAmount(''),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{t('pos.daily_retail')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <Field label={t('pos.amount')} htmlFor="dr_amount">
                    <Input
                        id="dr_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        inputMode="decimal"
                        dir="ltr"
                        value={amount}
                        onChange={(e) => setAmount(e.target.value)}
                    />
                </Field>
                <div className="grid grid-cols-2 gap-2">
                    <Field label={t('common.date')} htmlFor="dr_date">
                        <Input id="dr_date" type="date" dir="ltr" value={date} onChange={(e) => setDate(e.target.value)} />
                    </Field>
                    <Field label={t('invoices.method')} htmlFor="dr_method">
                        <NativeSelect id="dr_method" value={method} onChange={(e) => setMethod(e.target.value)}>
                            <option value="cash">{t('invoices.method.cash')}</option>
                            <option value="mada">{t('invoices.method.mada')}</option>
                        </NativeSelect>
                    </Field>
                </div>
                <Button variant="outline" disabled={!amount || saving} onClick={submit}>
                    {saving && <LoaderCircle className="size-4 animate-spin" />}
                    {t('pos.add_daily')}
                </Button>
            </CardContent>
        </Card>
    );
}
