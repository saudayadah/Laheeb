import { EmptyState } from '@/components/empty-state';
import { Field, NativeSelect } from '@/components/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronLeft, LoaderCircle, Lock, Truck, Wallet } from 'lucide-react';
import { useState } from 'react';

interface Stop {
    id: number;
    code: string;
    name: string;
    route: string | null;
    payment_term: string;
    phone: string | null;
    invoice: { id: number; status: string; total: string; payment_method: string } | null;
}

interface DeliveryIndexProps {
    stops: Stop[];
    date: string;
    doneCount: number;
    expenseCategories: { id: number; name_ar: string }[];
}

export default function DeliveryIndex({ stops, date, doneCount, expenseCategories }: DeliveryIndexProps) {
    const { t } = useTrans();
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={[{ title: t('delivery.title'), href: '/delivery' }]}>
            <Head title={t('delivery.title')} />
            <div className="mx-auto flex w-full max-w-xl flex-col gap-3 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-semibold">{t('delivery.title')}</h1>
                    <div className="flex items-center gap-2 text-sm">
                        <Badge variant="secondary" className="text-emerald-700 dark:text-emerald-400">
                            {t('delivery.done')}: {doneCount}
                        </Badge>
                        <Badge variant="secondary">
                            {t('delivery.pending')}: {stops.length - doneCount}
                        </Badge>
                    </div>
                </div>

                {stops.length === 0 ? (
                    <EmptyState icon={Truck} message={t('delivery.no_stops')} />
                ) : (
                    <div className="flex flex-col gap-2">
                        {stops.map((stop, i) => {
                            const posted = stop.invoice?.status === 'posted';
                            return (
                                <Link
                                    key={stop.id}
                                    href={route('delivery.stop', stop.id)}
                                    className={`flex items-center gap-3 rounded-xl border p-3 active:scale-[0.99] ${
                                        posted ? 'bg-emerald-50/60 dark:bg-emerald-950/30' : 'bg-card'
                                    }`}
                                >
                                    <div
                                        className={`flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold ${
                                            posted
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300'
                                                : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        {posted ? <CheckCircle2 className="size-5" /> : i + 1}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">{stop.name}</div>
                                        <div className="text-muted-foreground text-xs">
                                            {stop.route} · {t(`customers.term.${stop.payment_term}`)}
                                        </div>
                                    </div>
                                    {posted && stop.invoice && (
                                        <div className="text-end">
                                            <div className="font-semibold tabular-nums">{fmtAmount(stop.invoice.total)}</div>
                                            <div className="text-muted-foreground text-xs">{t(`invoices.method.${stop.invoice.payment_method}`)}</div>
                                        </div>
                                    )}
                                    <ChevronLeft className="text-muted-foreground size-4 shrink-0 ltr:rotate-180 rtl:rotate-0" />
                                </Link>
                            );
                        })}
                    </div>
                )}
                <div className="grid grid-cols-2 gap-2">
                    <Button variant="outline" className="h-12" asChild>
                        <Link href={route('closes.create', { type: 'driver', id: auth.user?.id, date })}>
                            <Lock className="size-4" /> {t('closes.my_close')}
                        </Link>
                    </Button>
                    <DriverExpenseButton categories={expenseCategories} />
                </div>

                <p className="text-muted-foreground text-center text-xs" dir="ltr">
                    {date}
                </p>
            </div>
        </AppLayout>
    );
}

/** Quick expense from the driver's own cash (fuel, repairs...). */
function DriverExpenseButton({ categories }: { categories: { id: number; name_ar: string }[] }) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const [categoryId, setCategoryId] = useState('');
    const [amount, setAmount] = useState('');
    const [note, setNote] = useState('');
    const [photo, setPhoto] = useState<File | null>(null);
    const [saving, setSaving] = useState(false);

    const submit = () => {
        setSaving(true);
        router.post(
            route('delivery.expense'),
            { expense_category_id: categoryId, amount, note: note || null, photo },
            {
                forceFormData: true,
                onSuccess: () => {
                    setOpen(false);
                    setAmount('');
                    setNote('');
                    setPhoto(null);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Button variant="outline" className="h-12" onClick={() => setOpen(true)}>
                <Wallet className="size-4" /> {t('expenses.my_expense')}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('expenses.my_expense')}</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-3">
                        <Field label={t('expenses.category')} htmlFor="de_cat">
                            <NativeSelect id="de_cat" value={categoryId} onChange={(e) => setCategoryId(e.target.value)}>
                                <option value="">—</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name_ar}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label={t('expenses.amount')} htmlFor="de_amount">
                            <Input
                                id="de_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                inputMode="decimal"
                                className="h-12 text-center text-lg"
                                value={amount}
                                onChange={(e) => setAmount(e.target.value)}
                            />
                        </Field>
                        <Field label={t('common.notes')} htmlFor="de_note" optional>
                            <Input id="de_note" value={note} onChange={(e) => setNote(e.target.value)} />
                        </Field>
                        <Field label={t('expenses.photo')} htmlFor="de_photo" optional>
                            <input
                                id="de_photo"
                                type="file"
                                accept="image/*"
                                capture="environment"
                                className="text-muted-foreground w-full rounded-md border p-2 text-sm"
                                onChange={(e) => setPhoto(e.target.files?.[0] ?? null)}
                            />
                        </Field>
                    </div>
                    <DialogFooter className="gap-2">
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={submit} disabled={saving || !amount || !categoryId}>
                            {saving && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
