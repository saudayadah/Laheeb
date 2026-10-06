import { EmptyState } from '@/components/empty-state';
import { Field, NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtInt } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type IdName, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Camera, Check, FileSpreadsheet, LoaderCircle, Plus, Settings, Wallet, X } from 'lucide-react';
import { useState } from 'react';

interface ExpenseRow {
    id: number;
    date: string;
    category: string | null;
    amount: string;
    paid_from: string;
    payer: string | null;
    supplier: string | null;
    status: string;
    note: string | null;
    has_photo: boolean;
}

interface CategoryOption {
    id: number;
    name_ar: string;
    kind: string;
}

interface ExpensesPageProps {
    expenses: Paginated<ExpenseRow>;
    filters: { from: string; to: string; category_id: string | null; paid_from: string | null; status: string | null };
    summary: { total: string; cash: string; bank: string; supplier_credit: string; pending: number };
    categories: CategoryOption[];
    suppliers: IdName[];
    drivers: IdName[];
    vehicles: IdName[];
    canApprove: boolean;
}

export default function ExpensesIndex({ expenses, filters, summary, categories, suppliers, drivers, vehicles, canApprove }: ExpensesPageProps) {
    const { t } = useTrans();
    const [createOpen, setCreateOpen] = useState(false);
    const [voidId, setVoidId] = useState<number | null>(null);
    const [voidReason, setVoidReason] = useState('');

    const apply = (updates: Partial<ExpensesPageProps['filters']>) => {
        const next = { ...filters, ...updates };
        router.get(route('expenses.index'), Object.fromEntries(Object.entries(next).filter(([, v]) => v !== null && v !== '')), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const submitVoid = () => {
        if (!voidId || !voidReason) return;
        router.post(
            route('expenses.void', voidId),
            { reason: voidReason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setVoidId(null);
                    setVoidReason('');
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('expenses.title'), href: '/expenses' }]}>
            <Head title={t('expenses.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('expenses.title')}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('expenses.sheet')}>
                                    <FileSpreadsheet className="size-4" /> {t('expenses.sheet')}
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('expenses.categories')}>
                                    <Settings className="size-4" /> {t('expenses.categories')}
                                </Link>
                            </Button>
                            <Button size="sm" onClick={() => setCreateOpen(true)}>
                                <Plus className="size-4" /> {t('expenses.create')}
                            </Button>
                        </div>
                    }
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <div className="bg-accent/40 rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(summary.total)}</div>
                        <div className="text-muted-foreground text-xs">{t('expenses.total_period')}</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(summary.cash)}</div>
                        <div className="text-muted-foreground text-xs">{t('expenses.from_cash')}</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(summary.bank)}</div>
                        <div className="text-muted-foreground text-xs">{t('expenses.from_bank')}</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(summary.supplier_credit)}</div>
                        <div className="text-muted-foreground text-xs">{t('expenses.on_credit')}</div>
                    </div>
                    <div className={`rounded-xl border p-4 ${summary.pending > 0 ? 'border-amber-300 dark:border-amber-800' : ''}`}>
                        <div className={`text-2xl font-bold tabular-nums ${summary.pending > 0 ? 'text-amber-600 dark:text-amber-400' : ''}`}>
                            {fmtInt(summary.pending)}
                        </div>
                        <div className="text-muted-foreground text-xs">{t('expenses.pending_count')}</div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Input type="date" dir="ltr" className="w-38" value={filters.from} onChange={(e) => apply({ from: e.target.value })} />
                    <span className="text-muted-foreground">—</span>
                    <Input type="date" dir="ltr" className="w-38" value={filters.to} onChange={(e) => apply({ to: e.target.value })} />
                    <NativeSelect className="w-40" value={filters.category_id ?? ''} onChange={(e) => apply({ category_id: e.target.value || null })}>
                        <option value="">
                            {t('expenses.category')}: {t('common.all')}
                        </option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name_ar}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect className="w-40" value={filters.paid_from ?? ''} onChange={(e) => apply({ paid_from: e.target.value || null })}>
                        <option value="">
                            {t('expenses.paid_from')}: {t('common.all')}
                        </option>
                        {['driver_cash', 'counter_cash', 'bank', 'supplier_credit'].map((s) => (
                            <option key={s} value={s}>
                                {t(`expenses.paid_from.${s}`)}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect className="w-40" value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value || null })}>
                        <option value="">
                            {t('common.status')}: {t('common.all')}
                        </option>
                        <option value="pending">{t('expenses.status.pending')}</option>
                        <option value="approved">{t('expenses.status.approved')}</option>
                        <option value="void">{t('expenses.status.void')}</option>
                    </NativeSelect>
                </div>

                {expenses.data.length === 0 ? (
                    <EmptyState icon={Wallet} message={t('common.no_results')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.date')}</TableHead>
                                    <TableHead>{t('expenses.category')}</TableHead>
                                    <TableHead>{t('expenses.paid_from')}</TableHead>
                                    <TableHead>{t('common.notes')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="text-end">{t('expenses.amount')}</TableHead>
                                    <TableHead className="w-24" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {expenses.data.map((expense) => (
                                    <TableRow key={expense.id} className={expense.status === 'void' ? 'opacity-50' : ''}>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {expense.date}
                                        </TableCell>
                                        <TableCell className="font-medium">{expense.category}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {t(`expenses.paid_from.${expense.paid_from}`)}
                                                {expense.payer && <span className="ms-1">· {expense.payer}</span>}
                                                {expense.supplier && <span className="ms-1">· {expense.supplier}</span>}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground max-w-48 truncate">
                                            {expense.note}
                                            {expense.has_photo && (
                                                <a
                                                    href={route('expenses.photo', expense.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="ms-1 inline-block align-middle"
                                                >
                                                    <Camera className="size-3.5" />
                                                </a>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={expense.status === 'approved' ? 'secondary' : 'outline'}
                                                className={expense.status === 'pending' ? 'border-amber-300 text-amber-700 dark:text-amber-400' : ''}
                                            >
                                                {t(`expenses.status.${expense.status}`)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell
                                            className={`text-end font-semibold tabular-nums ${expense.status === 'void' ? 'line-through' : ''}`}
                                        >
                                            {fmtAmount(expense.amount)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-1">
                                                {expense.status === 'pending' && canApprove && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-emerald-600"
                                                        onClick={() =>
                                                            router.post(route('expenses.approve', expense.id), {}, { preserveScroll: true })
                                                        }
                                                    >
                                                        <Check className="size-4" />
                                                    </Button>
                                                )}
                                                {expense.status !== 'void' && canApprove && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground size-8"
                                                        onClick={() => setVoidId(expense.id)}
                                                    >
                                                        <X className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination paginator={expenses} />

                <ExpenseDialog
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                    categories={categories}
                    suppliers={suppliers}
                    drivers={drivers}
                    vehicles={vehicles}
                />

                <Dialog open={voidId !== null} onOpenChange={(o) => !o && setVoidId(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('invoices.void_reason')}</DialogTitle>
                        </DialogHeader>
                        <Input value={voidReason} onChange={(e) => setVoidReason(e.target.value)} />
                        <DialogFooter className="gap-2">
                            <Button variant="outline" onClick={() => setVoidId(null)}>
                                {t('common.cancel')}
                            </Button>
                            <Button variant="destructive" onClick={submitVoid} disabled={!voidReason}>
                                {t('common.delete')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}

export function ExpenseDialog({
    open,
    onOpenChange,
    categories,
    suppliers,
    drivers,
    vehicles = [],
    presetDate,
    presetCategory,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    categories: CategoryOption[];
    suppliers: IdName[];
    drivers: IdName[];
    vehicles?: IdName[];
    presetDate?: string;
    presetCategory?: number;
}) {
    const { t } = useTrans();
    const [date, setDate] = useState(presetDate ?? new Date().toISOString().slice(0, 10));
    const [categoryId, setCategoryId] = useState(presetCategory ? String(presetCategory) : '');
    const [amount, setAmount] = useState('');
    const [paidFrom, setPaidFrom] = useState('counter_cash');
    const [paidBy, setPaidBy] = useState('');
    const [supplierId, setSupplierId] = useState('');
    const [vehicleId, setVehicleId] = useState('');
    const [note, setNote] = useState('');
    const [photo, setPhoto] = useState<File | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = () => {
        setSaving(true);
        setError(null);
        router.post(
            route('expenses.store'),
            {
                expense_date: date,
                expense_category_id: categoryId,
                amount,
                paid_from: paidFrom,
                paid_by: paidFrom === 'driver_cash' ? paidBy || null : null,
                supplier_id: paidFrom === 'supplier_credit' ? supplierId || null : null,
                vehicle_id: vehicleId || null,
                note: note || null,
                from_sheet: presetDate ? 1 : 0,
                photo,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    setAmount('');
                    setNote('');
                    setPhoto(null);
                },
                onError: (errors) => setError(Object.values(errors)[0] as string),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{t('expenses.create')}</DialogTitle>
                </DialogHeader>
                <div className="grid gap-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('common.date')} htmlFor="e_date">
                            <Input id="e_date" type="date" dir="ltr" value={date} onChange={(e) => setDate(e.target.value)} />
                        </Field>
                        <Field label={t('expenses.category')} htmlFor="e_cat">
                            <NativeSelect id="e_cat" value={categoryId} onChange={(e) => setCategoryId(e.target.value)}>
                                <option value="">—</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name_ar}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label={t('expenses.amount')} htmlFor="e_amount">
                            <Input
                                id="e_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                inputMode="decimal"
                                value={amount}
                                onChange={(e) => setAmount(e.target.value)}
                            />
                        </Field>
                        <Field label={t('expenses.paid_from')} htmlFor="e_source">
                            <NativeSelect id="e_source" value={paidFrom} onChange={(e) => setPaidFrom(e.target.value)}>
                                {['counter_cash', 'driver_cash', 'bank', 'supplier_credit'].map((s) => (
                                    <option key={s} value={s}>
                                        {t(`expenses.paid_from.${s}`)}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        {paidFrom === 'driver_cash' && (
                            <Field label={t('expenses.payer')} htmlFor="e_payer">
                                <NativeSelect id="e_payer" value={paidBy} onChange={(e) => setPaidBy(e.target.value)}>
                                    <option value="">—</option>
                                    {drivers.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            {d.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        )}
                        {paidFrom === 'supplier_credit' && (
                            <Field label={t('nav.suppliers')} htmlFor="e_supplier">
                                <NativeSelect id="e_supplier" value={supplierId} onChange={(e) => setSupplierId(e.target.value)}>
                                    <option value="">—</option>
                                    {suppliers.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        )}
                    </div>
                    {vehicles.length > 0 && (
                        <Field label={t('nav.vehicles')} htmlFor="e_vehicle" optional>
                            <NativeSelect id="e_vehicle" value={vehicleId} onChange={(e) => setVehicleId(e.target.value)}>
                                <option value="">—</option>
                                {vehicles.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    )}
                    <Field label={t('common.notes')} htmlFor="e_note" optional>
                        <Input id="e_note" value={note} onChange={(e) => setNote(e.target.value)} />
                    </Field>
                    <Field label={t('expenses.photo')} htmlFor="e_photo" optional>
                        <input
                            id="e_photo"
                            type="file"
                            accept="image/*"
                            className="text-muted-foreground w-full rounded-md border p-2 text-sm"
                            onChange={(e) => setPhoto(e.target.files?.[0] ?? null)}
                        />
                    </Field>
                    {error && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                            {error}
                        </div>
                    )}
                </div>
                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        {t('common.cancel')}
                    </Button>
                    <Button onClick={submit} disabled={saving || !amount || !categoryId}>
                        {saving && <LoaderCircle className="size-4 animate-spin" />}
                        {t('common.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
