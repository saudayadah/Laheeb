import { Field, NativeSelect } from '@/components/field';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtPrice } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Ban, LoaderCircle, Printer, ReceiptText, Repeat, Undo2 } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface InvoiceLineData {
    id: number;
    product_id: number | null;
    name: string | null;
    qty: number;
    unit_price: string;
    vat_rate: string;
    line_subtotal: string;
    line_vat: string;
    line_total: string;
}

export interface InvoiceData {
    id: number;
    number: string;
    uuid: string;
    date: string;
    posted_at: string | null;
    type: string;
    source: string;
    payment_method: string;
    status: string;
    subtotal: string;
    vat_amount: string;
    total: string;
    prices_include_vat: boolean;
    qr_payload: string | null;
    void_reason: string | null;
    reclass_reason: string | null;
    notes: string | null;
    driver: string | null;
    customer: { id: number; code: string; name: string; vat_number: string | null; national_address: string | null } | null;
    lines: InvoiceLineData[];
    credit_notes: { id: number; number: string; date: string; reason: string; total: string }[];
}

interface ShowProps {
    invoice: InvoiceData;
    canVoid: boolean;
    canReclassify: boolean;
}

export default function InvoiceShow({ invoice, canVoid, canReclassify }: ShowProps) {
    const { t } = useTrans();
    const [voidOpen, setVoidOpen] = useState(false);
    const [reclassOpen, setReclassOpen] = useState(false);
    const [returnOpen, setReturnOpen] = useState(false);

    const posted = invoice.status === 'posted';

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('invoices.title'), href: '/invoices' },
                { title: invoice.number, href: '#' },
            ]}
        >
            <Head title={invoice.number} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
                <PageHeader
                    title={`${invoice.number}`}
                    description={`${invoice.date} · ${invoice.customer?.name ?? t('invoices.walk_in')}`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('invoices.thermal', invoice.id)}>
                                    <ReceiptText className="size-4" /> {t('invoices.thermal')}
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('invoices.print', invoice.id)}>
                                    <Printer className="size-4" /> {t('invoices.print_a4')}
                                </Link>
                            </Button>
                            {posted && (
                                <Button variant="outline" size="sm" onClick={() => setReturnOpen(true)}>
                                    <Undo2 className="size-4" /> {t('invoices.credit_note')}
                                </Button>
                            )}
                            {posted && canReclassify && (
                                <Button variant="outline" size="sm" onClick={() => setReclassOpen(true)}>
                                    <Repeat className="size-4" /> {t('invoices.reclassify')}
                                </Button>
                            )}
                            {posted && canVoid && (
                                <Button variant="destructive" size="sm" onClick={() => setVoidOpen(true)}>
                                    <Ban className="size-4" /> {t('invoices.void')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{t(`invoices.status.${invoice.status}`)}</Badge>
                    <Badge variant="outline">{t(`invoices.method.${invoice.payment_method}`)}</Badge>
                    <Badge variant="outline">{t(`invoices.source.${invoice.source}`)}</Badge>
                    {invoice.driver && (
                        <Badge variant="outline">
                            {t('invoices.driver')}: {invoice.driver}
                        </Badge>
                    )}
                    {invoice.void_reason && (
                        <Badge variant="outline" className="border-red-300 text-red-700 dark:text-red-400">
                            {t('invoices.void_reason')}: {invoice.void_reason}
                        </Badge>
                    )}
                    {invoice.reclass_reason && (
                        <Badge variant="outline" className="border-amber-300 text-amber-700 dark:text-amber-400">
                            {t('invoices.reclassify')}: {invoice.reclass_reason}
                        </Badge>
                    )}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('invoices.item')}</TableHead>
                                <TableHead className="text-end">{t('invoices.qty')}</TableHead>
                                <TableHead className="text-end">{t('invoices.price')}</TableHead>
                                <TableHead className="text-end">{t('invoices.vat')}</TableHead>
                                <TableHead className="text-end">{t('invoices.line_total')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invoice.lines.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell className="font-medium">{line.name}</TableCell>
                                    <TableCell className="text-end tabular-nums">{line.qty}</TableCell>
                                    <TableCell className="text-end tabular-nums">{fmtPrice(line.unit_price)}</TableCell>
                                    <TableCell className="text-muted-foreground text-end tabular-nums">{fmtAmount(line.line_vat)}</TableCell>
                                    <TableCell className="text-end font-medium tabular-nums">{fmtAmount(line.line_total)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <div className="flex flex-col items-end gap-1 border-t p-4 text-sm">
                        <div className="flex w-56 justify-between">
                            <span className="text-muted-foreground">{t('invoices.subtotal')}</span>
                            <span className="tabular-nums">{fmtAmount(invoice.subtotal)}</span>
                        </div>
                        <div className="flex w-56 justify-between">
                            <span className="text-muted-foreground">{t('invoices.vat')}</span>
                            <span className="tabular-nums">{fmtAmount(invoice.vat_amount)}</span>
                        </div>
                        <div className="flex w-56 justify-between text-base font-bold">
                            <span>{t('invoices.total')}</span>
                            <span className="tabular-nums">{fmtAmount(invoice.total)}</span>
                        </div>
                    </div>
                </div>

                {invoice.credit_notes.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('invoices.credit_note')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('invoices.number')}</TableHead>
                                        <TableHead>{t('common.date')}</TableHead>
                                        <TableHead>{t('invoices.reason')}</TableHead>
                                        <TableHead className="text-end">{t('invoices.total')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invoice.credit_notes.map((note) => (
                                        <TableRow key={note.id}>
                                            <TableCell className="tabular-nums" dir="ltr">
                                                {note.number}
                                            </TableCell>
                                            <TableCell className="tabular-nums" dir="ltr">
                                                {note.date}
                                            </TableCell>
                                            <TableCell>{note.reason}</TableCell>
                                            <TableCell className="text-end tabular-nums">{fmtAmount(note.total)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                <VoidDialog open={voidOpen} onOpenChange={setVoidOpen} invoiceId={invoice.id} />
                <ReclassifyDialog
                    key={`${invoice.payment_method}-${reclassOpen}`}
                    open={reclassOpen}
                    onOpenChange={setReclassOpen}
                    invoiceId={invoice.id}
                    current={invoice.payment_method}
                />
                <ReturnDialog open={returnOpen} onOpenChange={setReturnOpen} invoice={invoice} />
            </div>
        </AppLayout>
    );
}

function VoidDialog({ open, onOpenChange, invoiceId }: { open: boolean; onOpenChange: (o: boolean) => void; invoiceId: number }) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors } = useForm({ reason: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invoices.void', invoiceId), { preserveScroll: true, onSuccess: () => onOpenChange(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('invoices.void')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label={t('invoices.void_reason')} htmlFor="void_reason" error={errors.reason}>
                        <Input id="void_reason" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
                    </Field>
                    <InputError message={(errors as Record<string, string>).invoice} />
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('invoices.void')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ReclassifyDialog({
    open,
    onOpenChange,
    invoiceId,
    current,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    invoiceId: number;
    current: string;
}) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors } = useForm({
        payment_method: current === 'cash' ? 'credit' : 'cash',
        reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invoices.reclassify', invoiceId), { preserveScroll: true, onSuccess: () => onOpenChange(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('invoices.reclassify')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label={t('invoices.method')} htmlFor="reclass_method" error={errors.payment_method}>
                        <NativeSelect id="reclass_method" value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)}>
                            {['cash', 'mada', 'credit']
                                .filter((m) => m !== current)
                                .map((m) => (
                                    <option key={m} value={m}>
                                        {t(`invoices.method.${m}`)}
                                    </option>
                                ))}
                        </NativeSelect>
                    </Field>
                    <Field label={t('invoices.reclass_reason')} htmlFor="reclass_reason" error={errors.reason}>
                        <Input id="reclass_reason" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
                    </Field>
                    <InputError message={(errors as Record<string, string>).invoice} />
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

function ReturnDialog({ open, onOpenChange, invoice }: { open: boolean; onOpenChange: (o: boolean) => void; invoice: InvoiceData }) {
    const { t } = useTrans();
    const [qtys, setQtys] = useState<Record<number, string>>({});
    const [condition, setCondition] = useState('good');
    const [reason, setReason] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // One key per dialog opening: a retry after a network hiccup reuses it,
    // so the server can never post the same return twice.
    const idempotencyKey = useMemo(() => crypto.randomUUID(), [open]); // eslint-disable-line react-hooks/exhaustive-deps

    const productLines = invoice.lines.filter((line) => line.product_id !== null);

    const submit = () => {
        const items = productLines
            .map((line) => ({ product_id: line.product_id, qty: parseInt(qtys[line.id] ?? '', 10) || 0, condition }))
            .filter((item) => item.qty > 0);
        if (items.length === 0) return;

        setSaving(true);
        setError(null);
        router.post(
            route('invoices.credit-note', invoice.id),
            { items, reason: reason || t('invoices.return_reason'), idempotency_key: idempotencyKey },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    setQtys({});
                    setReason('');
                },
                onError: (e) => setError(Object.values(e)[0] as string),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('invoices.credit_note')}</DialogTitle>
                </DialogHeader>
                <div className="grid gap-3">
                    {productLines.map((line) => (
                        <div key={line.id} className="flex items-center gap-3">
                            <span className="min-w-0 flex-1 truncate text-sm">{line.name}</span>
                            <span className="text-muted-foreground text-xs tabular-nums">/ {line.qty}</span>
                            <Input
                                type="text"
                                inputMode="numeric"
                                dir="ltr"
                                className="w-24 text-center"
                                placeholder="0"
                                value={qtys[line.id] ?? ''}
                                onChange={(e) => setQtys((q) => ({ ...q, [line.id]: e.target.value.replace(/[^\d]/g, '') }))}
                            />
                        </div>
                    ))}
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('delivery.returns')} htmlFor="ret_condition">
                            <NativeSelect id="ret_condition" value={condition} onChange={(e) => setCondition(e.target.value)}>
                                <option value="good">{t('delivery.returns_good')}</option>
                                <option value="damaged">{t('delivery.returns_damaged')}</option>
                            </NativeSelect>
                        </Field>
                        <Field label={t('invoices.reason')} htmlFor="ret_reason" optional>
                            <Input id="ret_reason" value={reason} onChange={(e) => setReason(e.target.value)} />
                        </Field>
                    </div>
                    {error && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                            {error}
                        </div>
                    )}
                </div>
                <DialogFooter className="gap-2">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        {t('common.cancel')}
                    </Button>
                    <Button onClick={submit} disabled={saving}>
                        {saving && <LoaderCircle className="size-4 animate-spin" />}
                        {t('common.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
