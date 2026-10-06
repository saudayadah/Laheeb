import { EmptyState } from '@/components/empty-state';
import { Field, NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { ActiveBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { HandCoins, LoaderCircle, Pencil, Plus, Warehouse } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface SupplierRow {
    id: number;
    name: string;
    phone: string | null;
    vat_number: string | null;
    active: boolean;
    payable: string;
    notes: string | null;
}

interface SuppliersPageProps {
    suppliers: SupplierRow[];
    totalPayable: string;
}

export default function SuppliersIndex({ suppliers, totalPayable }: SuppliersPageProps) {
    const { t } = useTrans();
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<SupplierRow | null>(null);
    const [paying, setPaying] = useState<SupplierRow | null>(null);

    return (
        <AppLayout breadcrumbs={[{ title: t('suppliers.title'), href: '/suppliers' }]}>
            <Head title={t('suppliers.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('suppliers.title')}
                    actions={
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setFormOpen(true);
                            }}
                        >
                            <Plus className="size-4" /> {t('suppliers.create')}
                        </Button>
                    }
                />

                <div className="bg-accent/40 max-w-sm rounded-xl border p-4">
                    <div className="text-3xl font-bold tabular-nums">{fmtAmount(totalPayable)}</div>
                    <div className="text-muted-foreground mt-1 text-sm">
                        {t('suppliers.total_payable')} ({t('common.currency')})
                    </div>
                </div>

                {suppliers.length === 0 ? (
                    <EmptyState icon={Warehouse} message={t('suppliers.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('customers.phone')}</TableHead>
                                    <TableHead className="text-end">{t('suppliers.payable')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="w-32 text-end">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {suppliers.map((supplier) => (
                                    <TableRow key={supplier.id}>
                                        <TableCell>
                                            <Link href={route('suppliers.show', supplier.id)} className="font-medium hover:underline">
                                                {supplier.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {supplier.phone ?? '—'}
                                        </TableCell>
                                        <TableCell
                                            className={`text-end font-bold tabular-nums ${parseFloat(supplier.payable) > 0 ? 'text-red-600 dark:text-red-400' : ''}`}
                                        >
                                            {fmtAmount(supplier.payable)}
                                        </TableCell>
                                        <TableCell>
                                            <ActiveBadge active={supplier.active} />
                                        </TableCell>
                                        <TableCell className="text-end">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button variant="outline" size="sm" onClick={() => setPaying(supplier)}>
                                                    <HandCoins className="size-4" /> {t('suppliers.pay')}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    onClick={() => {
                                                        setEditing(supplier);
                                                        setFormOpen(true);
                                                    }}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <SupplierDialog key={editing?.id ?? 'new'} open={formOpen} onOpenChange={setFormOpen} supplier={editing} />
                {paying && <PayDialog supplier={paying} onClose={() => setPaying(null)} />}
            </div>
        </AppLayout>
    );
}

function SupplierDialog({ open, onOpenChange, supplier }: { open: boolean; onOpenChange: (o: boolean) => void; supplier: SupplierRow | null }) {
    const { t } = useTrans();
    const isEdit = supplier !== null;
    const { data, setData, post, patch, processing, errors } = useForm({
        name: supplier?.name ?? '',
        phone: supplier?.phone ?? '',
        vat_number: supplier?.vat_number ?? '',
        notes: supplier?.notes ?? '',
        active: supplier?.active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (isEdit) patch(route('suppliers.update', supplier.id), options);
        else post(route('suppliers.store'), options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? t('suppliers.edit') : t('suppliers.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <Field label={t('common.name')} htmlFor="s_name" error={errors.name}>
                        <Input id="s_name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('customers.phone')} htmlFor="s_phone" error={errors.phone} optional>
                            <Input id="s_phone" dir="ltr" value={data.phone ?? ''} onChange={(e) => setData('phone', e.target.value)} />
                        </Field>
                        <Field label={t('customers.vat_number')} htmlFor="s_vat" error={errors.vat_number} optional>
                            <Input id="s_vat" dir="ltr" value={data.vat_number ?? ''} onChange={(e) => setData('vat_number', e.target.value)} />
                        </Field>
                    </div>
                    <Field label={t('common.notes')} htmlFor="s_notes" error={errors.notes} optional>
                        <Input id="s_notes" value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                    <div className="flex items-center gap-2">
                        <Checkbox id="s_active" checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                        <label htmlFor="s_active" className="text-sm font-medium">
                            {t('common.active')}
                        </label>
                    </div>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PayDialog({ supplier, onClose }: { supplier: SupplierRow; onClose: () => void }) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors } = useForm({
        amount: '',
        payment_date: new Date().toISOString().slice(0, 10),
        method: 'bank',
        reference: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('suppliers.pay', supplier.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('suppliers.pay')} — {supplier.name}
                    </DialogTitle>
                </DialogHeader>
                <p className="text-muted-foreground text-sm">
                    {t('suppliers.payable')}: <span className="font-bold tabular-nums">{fmtAmount(supplier.payable)}</span>
                </p>
                <form onSubmit={submit} className="grid gap-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('expenses.amount')} htmlFor="p_amount" error={errors.amount}>
                            <Input
                                id="p_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label={t('common.date')} htmlFor="p_date" error={errors.payment_date}>
                            <Input
                                id="p_date"
                                type="date"
                                dir="ltr"
                                value={data.payment_date}
                                onChange={(e) => setData('payment_date', e.target.value)}
                            />
                        </Field>
                        <Field label={t('invoices.method')} htmlFor="p_method" error={errors.method}>
                            <NativeSelect id="p_method" value={data.method} onChange={(e) => setData('method', e.target.value)}>
                                <option value="bank">{t('expenses.paid_from.bank')}</option>
                                <option value="counter_cash">{t('expenses.paid_from.counter_cash')}</option>
                            </NativeSelect>
                        </Field>
                        <Field label={t('receipts.reference')} htmlFor="p_ref" error={errors.reference} optional>
                            <Input id="p_ref" dir="ltr" value={data.reference} onChange={(e) => setData('reference', e.target.value)} />
                        </Field>
                    </div>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !data.amount}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
