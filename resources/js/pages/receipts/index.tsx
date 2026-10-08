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
import { fmtAmount, localToday } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type IdName, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { HandCoins, LoaderCircle, Plus, Printer } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

interface ReceiptRow {
    id: number;
    number: string;
    date: string;
    customer: { id: number; name: string; code: string } | null;
    group: IdName | null;
    amount: string;
    method: string;
    status: string;
    receiver: string | null;
    reference: string | null;
}

interface OpenInvoice {
    invoice_id: number;
    number: string;
    date: string;
    customer: string | null;
    total: string;
    open: string;
}

interface CustomerOption {
    id: number;
    name: string;
    code: string;
}

interface ReceiptsPageProps {
    receipts: Paginated<ReceiptRow>;
    filters: { from: string; to: string; search: string; method: string | null };
    summary: { total: string; cash: string; mada: string; transfer: string; count: number };
    customers: CustomerOption[];
    groups: IdName[];
}

export default function ReceiptsIndex({ receipts, filters, summary, customers, groups }: ReceiptsPageProps) {
    const { t } = useTrans();
    const [createOpen, setCreateOpen] = useState(false);

    const apply = (updates: Partial<ReceiptsPageProps['filters']>) => {
        const next = { ...filters, ...updates };
        router.get(route('receipts.index'), Object.fromEntries(Object.entries(next).filter(([, v]) => v !== null && v !== '')), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('receipts.title'), href: '/receipts' }]}>
            <Head title={t('receipts.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('receipts.title')}
                    actions={
                        <Button onClick={() => setCreateOpen(true)}>
                            <Plus className="size-4" /> {t('receipts.create')}
                        </Button>
                    }
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div className="border-primary/30 bg-primary/10 dark:bg-primary/15 rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(summary.total)}</div>
                        <div className="text-muted-foreground text-xs">{t('receipts.total_collected')}</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(summary.cash)}</div>
                        <div className="text-muted-foreground text-xs">{t('receipts.method.cash')}</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(summary.mada)}</div>
                        <div className="text-muted-foreground text-xs">{t('receipts.method.mada')}</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(summary.transfer)}</div>
                        <div className="text-muted-foreground text-xs">{t('receipts.method.transfer')}</div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        type="date"
                        dir="ltr"
                        className="w-38"
                        aria-label={t('common.from')}
                        value={filters.from}
                        onChange={(e) => apply({ from: e.target.value })}
                    />
                    <span className="text-muted-foreground">—</span>
                    <Input
                        type="date"
                        dir="ltr"
                        className="w-38"
                        aria-label={t('common.to')}
                        value={filters.to}
                        onChange={(e) => apply({ to: e.target.value })}
                    />
                    <NativeSelect className="w-36" value={filters.method ?? ''} onChange={(e) => apply({ method: e.target.value || null })}>
                        <option value="">
                            {t('invoices.method')}: {t('common.all')}
                        </option>
                        <option value="cash">{t('receipts.method.cash')}</option>
                        <option value="mada">{t('receipts.method.mada')}</option>
                        <option value="transfer">{t('receipts.method.transfer')}</option>
                    </NativeSelect>
                </div>

                {receipts.data.length === 0 ? (
                    <EmptyState icon={HandCoins} message={t('common.no_results')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('receipts.number')}</TableHead>
                                    <TableHead>{t('common.date')}</TableHead>
                                    <TableHead>{t('customers.name')}</TableHead>
                                    <TableHead>{t('invoices.method')}</TableHead>
                                    <TableHead>{t('receipts.received_by')}</TableHead>
                                    <TableHead className="text-end">{t('receipts.amount')}</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {receipts.data.map((receipt) => (
                                    <TableRow key={receipt.id} className={receipt.status === 'void' ? 'opacity-50' : ''}>
                                        <TableCell className="tabular-nums" dir="ltr">
                                            {receipt.number}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {receipt.date}
                                        </TableCell>
                                        <TableCell>
                                            {receipt.customer?.name ?? (
                                                <span>
                                                    {receipt.group?.name}
                                                    <Badge variant="outline" className="ms-2 text-[10px]">
                                                        {t('customers.group')}
                                                    </Badge>
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{t(`receipts.method.${receipt.method}`)}</Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{receipt.receiver ?? '—'}</TableCell>
                                        <TableCell
                                            className={`text-end font-semibold tabular-nums ${receipt.status === 'void' ? 'line-through' : ''}`}
                                        >
                                            {fmtAmount(receipt.amount)}
                                        </TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="icon" className="size-8" asChild>
                                                <Link href={route('receipts.voucher', receipt.id)}>
                                                    <Printer className="size-4" />
                                                    <span className="sr-only">{t('receipts.voucher')}</span>
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination paginator={receipts} />

                <CreateReceiptDialog open={createOpen} onOpenChange={setCreateOpen} customers={customers} groups={groups} />
            </div>
        </AppLayout>
    );
}

function CreateReceiptDialog({
    open,
    onOpenChange,
    customers,
    groups,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    customers: CustomerOption[];
    groups: IdName[];
}) {
    const { t } = useTrans();
    const [scope, setScope] = useState<'customer' | 'group'>('customer');
    const [customerId, setCustomerId] = useState('');
    const [groupId, setGroupId] = useState('');
    const [amount, setAmount] = useState('');
    const [date, setDate] = useState(() => localToday());
    const [method, setMethod] = useState('cash');
    const [reference, setReference] = useState('');
    const [openInvoices, setOpenInvoices] = useState<OpenInvoice[]>([]);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const idempotencyKey = useRef(crypto.randomUUID());

    // Load open invoices whenever the target changes.
    useEffect(() => {
        const id = scope === 'customer' ? customerId : groupId;
        if (!id) {
            setOpenInvoices([]);
            return;
        }
        // Guard against out-of-order responses: only the latest request may set state.
        let stale = false;
        const params = scope === 'customer' ? `customer_id=${id}` : `customer_group_id=${id}`;
        fetch(route('receipts.open-invoices') + '?' + params, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                if (!stale) setOpenInvoices(data.invoices ?? []);
            })
            .catch(() => {
                if (!stale) setOpenInvoices([]);
            });
        return () => {
            stale = true;
        };
    }, [scope, customerId, groupId]);

    // FIFO preview of where the money will land.
    const preview = useMemo(() => {
        let remaining = parseFloat(amount) || 0;
        const rows = openInvoices.map((invoice) => {
            const take = Math.min(parseFloat(invoice.open), remaining);
            remaining = Math.max(0, remaining - take);
            return { ...invoice, allocated: take > 0 ? take : 0 };
        });
        return { rows, onAccount: remaining };
    }, [openInvoices, amount]);

    const submit = () => {
        setSaving(true);
        setError(null);
        router.post(
            route('receipts.store'),
            {
                customer_id: scope === 'customer' ? customerId || null : null,
                customer_group_id: scope === 'group' ? groupId || null : null,
                amount,
                receipt_date: date,
                method,
                reference: reference || null,
                idempotency_key: idempotencyKey.current,
            },
            {
                onError: (errors) => setError(Object.values(errors)[0] as string),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('receipts.create')}</DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => setScope('customer')}
                            className={`h-10 rounded-lg border text-sm font-medium ${scope === 'customer' ? 'border-primary bg-primary text-primary-foreground' : ''}`}
                        >
                            {t('customers.name')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setScope('group')}
                            className={`h-10 rounded-lg border text-sm font-medium ${scope === 'group' ? 'border-primary bg-primary text-primary-foreground' : ''}`}
                        >
                            {t('customers.group')}
                        </button>
                    </div>

                    {scope === 'customer' ? (
                        <Field label={t('customers.name')} htmlFor="rc_customer">
                            <NativeSelect id="rc_customer" value={customerId} onChange={(e) => setCustomerId(e.target.value)}>
                                <option value="">—</option>
                                {customers.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name} ({c.code})
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    ) : (
                        <Field label={t('customers.group')} htmlFor="rc_group">
                            <NativeSelect id="rc_group" value={groupId} onChange={(e) => setGroupId(e.target.value)}>
                                <option value="">—</option>
                                {groups.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        {g.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('receipts.amount')} htmlFor="rc_amount">
                            <Input
                                id="rc_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                inputMode="decimal"
                                value={amount}
                                onChange={(e) => setAmount(e.target.value)}
                            />
                        </Field>
                        <Field label={t('common.date')} htmlFor="rc_date">
                            <Input id="rc_date" type="date" dir="ltr" value={date} onChange={(e) => setDate(e.target.value)} />
                        </Field>
                        <Field label={t('invoices.method')} htmlFor="rc_method">
                            <NativeSelect id="rc_method" value={method} onChange={(e) => setMethod(e.target.value)}>
                                <option value="cash">{t('receipts.method.cash')}</option>
                                <option value="mada">{t('receipts.method.mada')}</option>
                                <option value="transfer">{t('receipts.method.transfer')}</option>
                            </NativeSelect>
                        </Field>
                        <Field label={t('receipts.reference')} htmlFor="rc_ref" optional>
                            <Input id="rc_ref" dir="ltr" value={reference} onChange={(e) => setReference(e.target.value)} />
                        </Field>
                    </div>

                    {openInvoices.length > 0 && (
                        <div className="rounded-lg border p-3">
                            <div className="text-muted-foreground mb-2 text-xs font-medium">
                                {t('receipts.allocations')} — {t('receipts.auto_oldest')}
                            </div>
                            <div className="flex max-h-48 flex-col gap-1 overflow-y-auto text-sm">
                                {preview.rows.map((row) => (
                                    <div key={row.invoice_id} className="flex items-center justify-between gap-2">
                                        <span className="text-muted-foreground tabular-nums" dir="ltr">
                                            {row.number} · {row.date}
                                        </span>
                                        {row.customer && <span className="truncate text-xs">{row.customer}</span>}
                                        <span className="tabular-nums">
                                            {row.allocated > 0 ? (
                                                <span className="font-medium text-emerald-700 dark:text-emerald-400">{fmtAmount(row.allocated)}</span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    ({t('receipts.open')}: {fmtAmount(row.open)})
                                                </span>
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            {preview.onAccount > 0 && (
                                <div className="mt-2 border-t pt-2 text-xs">
                                    {t('receipts.on_account')}: <span className="font-semibold tabular-nums">{fmtAmount(preview.onAccount)}</span>
                                </div>
                            )}
                        </div>
                    )}

                    {error && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                            {error}
                        </div>
                    )}
                </div>
                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        {t('common.cancel')}
                    </Button>
                    <Button onClick={submit} disabled={saving || !amount || (scope === 'customer' ? !customerId : !groupId)}>
                        {saving && <LoaderCircle className="size-4 animate-spin" />}
                        {t('common.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
