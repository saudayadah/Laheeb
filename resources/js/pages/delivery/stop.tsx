import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtPrice } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, HandCoins, LoaderCircle, Printer, Undo2 } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

interface StopProduct {
    id: number;
    name_ar: string;
    unit_price: string;
}

interface StopProps {
    customer: { id: number; code: string; name: string; payment_term: string; balance: string };
    products: StopProduct[];
    qtys: Record<string, number>;
    invoice: { id: number; status: string; payment_method: string; total: string } | null;
    date: string;
}

export default function DeliveryStop({ customer, products, qtys, invoice, date }: StopProps) {
    const { t } = useTrans();
    const posted = invoice?.status === 'posted';

    const [quantities, setQuantities] = useState<Record<number, string>>(() =>
        Object.fromEntries(Object.entries(qtys).map(([k, v]) => [k, String(v)])),
    );
    const [showReturns, setShowReturns] = useState(false);
    const [returnsGood, setReturnsGood] = useState<Record<number, string>>({});
    const [returnsDamaged, setReturnsDamaged] = useState<Record<number, string>>({});
    const [payment, setPayment] = useState(invoice?.payment_method ?? customer.payment_term);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const idempotencyKey = useRef<string>(crypto.randomUUID());

    const total = useMemo(() => {
        return products.reduce((sum, p) => {
            const qty = parseInt(quantities[p.id] ?? '', 10) || 0;
            return sum + qty * parseFloat(p.unit_price);
        }, 0);
    }, [products, quantities]);

    const visibleProducts = products.filter((p) => (qtys[p.id] ?? 0) > 0 || (parseInt(quantities[p.id] ?? '', 10) || 0) > 0);
    const [showAll, setShowAll] = useState(visibleProducts.length === 0);
    const list = showAll ? products : visibleProducts;

    const submit = () => {
        setSubmitting(true);
        setError(null);
        router.post(
            route('delivery.post-stop', customer.id),
            {
                items: products.map((p) => ({ product_id: p.id, qty: parseInt(quantities[p.id] ?? '', 10) || 0 })).filter((item) => item.qty > 0),
                returns: products
                    .flatMap((p) => [
                        { product_id: p.id, qty: parseInt(returnsGood[p.id] ?? '', 10) || 0, condition: 'good' },
                        { product_id: p.id, qty: parseInt(returnsDamaged[p.id] ?? '', 10) || 0, condition: 'damaged' },
                    ])
                    .filter((r) => r.qty > 0),
                payment_method: payment,
                idempotency_key: idempotencyKey.current,
            },
            {
                onError: (errors) => setError(Object.values(errors)[0] as string),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('delivery.title'), href: '/delivery' },
                { title: customer.name, href: '#' },
            ]}
        >
            <Head title={customer.name} />
            <div className="mx-auto flex w-full max-w-xl flex-col gap-4 p-4 pb-28">
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <h1 className="text-lg font-semibold">{customer.name}</h1>
                        <div className="text-muted-foreground text-sm tabular-nums">{customer.code}</div>
                    </div>
                    <div className="text-end">
                        <Badge variant="outline" className={parseFloat(customer.balance) > 0 ? 'border-red-300 text-red-700 dark:text-red-400' : ''}>
                            {t('delivery.balance')}: <span className="tabular-nums">{fmtAmount(customer.balance)}</span>
                        </Badge>
                    </div>
                </div>

                {parseFloat(customer.balance) > 0 && <CollectCard customerId={customer.id} />}

                {posted && invoice ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-center dark:border-emerald-900 dark:bg-emerald-950/30">
                        <div className="text-emerald-700 dark:text-emerald-300">{t('delivery.already_posted')}</div>
                        <div className="text-3xl font-bold tabular-nums">{fmtAmount(invoice.total)}</div>
                        <div className="text-muted-foreground text-sm">{t(`invoices.method.${invoice.payment_method}`)}</div>
                        <div className="flex gap-2">
                            <Button asChild>
                                <Link href={route('invoices.thermal', invoice.id)}>
                                    <Printer className="size-4" /> {t('delivery.print_receipt')}
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route('delivery.index')}>
                                    <ArrowRight className="size-4 ltr:rotate-180" /> {t('common.back')}
                                </Link>
                            </Button>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="flex flex-col gap-2">
                            {list.map((p) => (
                                <div key={p.id} className="flex items-center gap-3 rounded-lg border p-2">
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium">{p.name_ar}</div>
                                        <div className="text-muted-foreground text-xs tabular-nums">{fmtPrice(p.unit_price)}</div>
                                    </div>
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        dir="ltr"
                                        aria-label={p.name_ar}
                                        className="border-input h-12 w-24 rounded-lg border text-center text-lg font-semibold tabular-nums"
                                        value={quantities[p.id] ?? ''}
                                        onFocus={(e) => e.target.select()}
                                        onChange={(e) => setQuantities((q) => ({ ...q, [p.id]: e.target.value.replace(/[^\d]/g, '') }))}
                                    />
                                </div>
                            ))}
                            {!showAll && (
                                <Button variant="ghost" size="sm" onClick={() => setShowAll(true)}>
                                    + {t('products.title')}
                                </Button>
                            )}
                        </div>

                        <Button variant="outline" size="sm" onClick={() => setShowReturns((s) => !s)}>
                            <Undo2 className="size-4" /> {t('delivery.show_returns')}
                        </Button>

                        {showReturns && (
                            <div className="flex flex-col gap-2 rounded-xl border border-dashed p-3">
                                <div className="text-muted-foreground grid grid-cols-[1fr_5rem_5rem] gap-2 text-xs font-medium">
                                    <span>{t('delivery.returns')}</span>
                                    <span className="text-center">{t('delivery.returns_good')}</span>
                                    <span className="text-center">{t('delivery.returns_damaged')}</span>
                                </div>
                                {products.map((p) => (
                                    <div key={p.id} className="grid grid-cols-[1fr_5rem_5rem] items-center gap-2">
                                        <span className="truncate text-sm">{p.name_ar}</span>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            dir="ltr"
                                            aria-label={`${p.name_ar} - ${t('delivery.returns_good')}`}
                                            className="border-input h-10 rounded-lg border text-center tabular-nums"
                                            value={returnsGood[p.id] ?? ''}
                                            onChange={(e) => setReturnsGood((r) => ({ ...r, [p.id]: e.target.value.replace(/[^\d]/g, '') }))}
                                        />
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            dir="ltr"
                                            aria-label={`${p.name_ar} - ${t('delivery.returns_damaged')}`}
                                            className="border-input h-10 rounded-lg border text-center tabular-nums"
                                            value={returnsDamaged[p.id] ?? ''}
                                            onChange={(e) => setReturnsDamaged((r) => ({ ...r, [p.id]: e.target.value.replace(/[^\d]/g, '') }))}
                                        />
                                    </div>
                                ))}
                            </div>
                        )}

                        <div>
                            <div className="text-muted-foreground mb-1 text-sm font-medium">{t('delivery.payment')}</div>
                            <div className="grid grid-cols-3 gap-2">
                                {(['cash', 'mada', 'credit'] as const).map((method) => (
                                    <button
                                        key={method}
                                        type="button"
                                        onClick={() => setPayment(method)}
                                        className={`h-12 rounded-lg border text-sm font-medium ${
                                            payment === method ? 'border-primary bg-primary text-primary-foreground' : 'bg-card'
                                        }`}
                                    >
                                        {t(`invoices.method.${method}`)}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {error && (
                            <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                                {error}
                            </div>
                        )}

                        {/* Fixed action bar */}
                        <div className="bg-background/95 fixed inset-x-0 bottom-0 z-40 border-t p-3 backdrop-blur">
                            <div className="mx-auto flex max-w-xl items-center gap-3">
                                <div className="flex-1">
                                    <div className="text-muted-foreground text-xs">{t('invoices.total')}</div>
                                    <div className="text-xl font-bold tabular-nums">{fmtAmount(total)}</div>
                                </div>
                                <Button size="lg" className="h-12 flex-1" onClick={submit} disabled={submitting || total <= 0}>
                                    {submitting && <LoaderCircle className="size-4 animate-spin" />}
                                    {t('delivery.post_invoice')}
                                </Button>
                            </div>
                        </div>
                    </>
                )}
                <p className="text-muted-foreground text-center text-xs" dir="ltr">
                    {date}
                </p>
            </div>
        </AppLayout>
    );
}

/** Collect a cash payment on the customer's old credit, right at the door. */
function CollectCard({ customerId }: { customerId: number }) {
    const { t } = useTrans();
    const [amount, setAmount] = useState('');
    const [saving, setSaving] = useState(false);

    const collect = () => {
        if (!amount) return;
        setSaving(true);
        router.post(route('delivery.collect', customerId), { amount, idempotency_key: crypto.randomUUID() }, { onFinish: () => setSaving(false) });
    };

    return (
        <div className="flex items-end gap-2 rounded-xl border border-dashed p-3">
            <div className="flex-1">
                <div className="text-muted-foreground mb-1 text-xs font-medium">{t('receipts.collect')}</div>
                <input
                    type="text"
                    inputMode="decimal"
                    dir="ltr"
                    aria-label={t('receipts.collect')}
                    className="border-input h-11 w-full rounded-lg border px-2 text-center text-lg font-semibold tabular-nums"
                    placeholder="0.00"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value.replace(/[^\d.]/g, ''))}
                />
            </div>
            <Button className="h-11" onClick={collect} disabled={!amount || saving}>
                {saving ? <LoaderCircle className="size-4 animate-spin" /> : <HandCoins className="size-4" />}
                {t('receipts.collect')}
            </Button>
        </div>
    );
}
