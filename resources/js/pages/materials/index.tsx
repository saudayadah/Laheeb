import { EmptyState } from '@/components/empty-state';
import { Field, NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtInt } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, LoaderCircle, Package, Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface MaterialRow {
    id: number;
    name: string;
    unit: string;
    on_hand: string;
    reorder_level: string | null;
    low: boolean;
    consumed_month: string;
    per_1000_loaves: string | null;
    active: boolean;
}

interface MovementRow {
    id: number;
    date: string;
    material: { id: number; name: string; unit: string } | null;
    direction: string;
    qty: string;
    notes: string | null;
}

interface MaterialsPageProps {
    materials: MaterialRow[];
    movements: MovementRow[];
    loavesThisMonth: number;
}

export default function MaterialsIndex({ materials, movements, loavesThisMonth }: MaterialsPageProps) {
    const { t } = useTrans();
    const [materialOpen, setMaterialOpen] = useState(false);
    const [editing, setEditing] = useState<MaterialRow | null>(null);
    const [movementOpen, setMovementOpen] = useState(false);
    const [movementDirection, setMovementDirection] = useState<'in' | 'out'>('in');

    return (
        <AppLayout breadcrumbs={[{ title: t('materials.title'), href: '/materials' }]}>
            <Head title={t('materials.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('materials.title')}
                    description={`${t('materials.loaves_month')}: ${fmtInt(loavesThisMonth)}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setMovementDirection('in');
                                    setMovementOpen(true);
                                }}
                            >
                                <ArrowDownToLine className="size-4" /> {t('materials.in')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setMovementDirection('out');
                                    setMovementOpen(true);
                                }}
                            >
                                <ArrowUpFromLine className="size-4" /> {t('materials.out')}
                            </Button>
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setMaterialOpen(true);
                                }}
                            >
                                <Plus className="size-4" /> {t('materials.create')}
                            </Button>
                        </div>
                    }
                />

                {materials.length === 0 ? (
                    <EmptyState icon={Package} message={t('materials.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead className="text-end">{t('materials.on_hand')}</TableHead>
                                    <TableHead className="text-end">{t('materials.consumed_month')}</TableHead>
                                    <TableHead className="text-end">{t('materials.per_1000')}</TableHead>
                                    <TableHead className="w-14" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {materials.map((material) => (
                                    <TableRow key={material.id}>
                                        <TableCell>
                                            <span className="font-medium">{material.name}</span>
                                            <span className="text-muted-foreground ms-2 text-xs">({material.unit})</span>
                                            {material.low && (
                                                <Badge variant="outline" className="ms-2 border-red-300 text-[10px] text-red-700 dark:text-red-400">
                                                    {t('materials.low')}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell
                                            className={`text-end text-base font-bold tabular-nums ${material.low ? 'text-red-600 dark:text-red-400' : ''}`}
                                        >
                                            {fmtAmount(material.on_hand)}
                                        </TableCell>
                                        <TableCell className="text-end tabular-nums">{fmtAmount(material.consumed_month)}</TableCell>
                                        <TableCell className="text-muted-foreground text-end tabular-nums">
                                            {material.per_1000_loaves ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() => {
                                                    setEditing(material);
                                                    setMaterialOpen(true);
                                                }}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <section className="flex flex-col gap-2">
                    <h2 className="font-semibold">{t('materials.recent')}</h2>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.date')}</TableHead>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead className="text-end">{t('orders.qty')}</TableHead>
                                    <TableHead>{t('common.notes')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {movements.map((movement) => (
                                    <TableRow key={movement.id}>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {movement.date}
                                        </TableCell>
                                        <TableCell className="font-medium">{movement.material?.name}</TableCell>
                                        <TableCell
                                            className={`text-end font-semibold tabular-nums ${
                                                movement.direction === 'in'
                                                    ? 'text-emerald-700 dark:text-emerald-400'
                                                    : 'text-red-600 dark:text-red-400'
                                            }`}
                                        >
                                            {movement.direction === 'in' ? '+' : '−'}
                                            {fmtAmount(movement.qty)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{movement.notes}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <MaterialDialog key={editing?.id ?? 'new'} open={materialOpen} onOpenChange={setMaterialOpen} material={editing} />
                <MovementDialog
                    key={`mv-${movementDirection}-${movementOpen}`}
                    open={movementOpen}
                    onOpenChange={setMovementOpen}
                    materials={materials}
                    direction={movementDirection}
                />
            </div>
        </AppLayout>
    );
}

function MaterialDialog({ open, onOpenChange, material }: { open: boolean; onOpenChange: (o: boolean) => void; material: MaterialRow | null }) {
    const { t } = useTrans();
    const isEdit = material !== null;
    const { data, setData, post, patch, processing, errors } = useForm({
        name: material?.name ?? '',
        unit: material?.unit ?? 'كيس',
        reorder_level: material?.reorder_level ?? '',
        active: material?.active ?? true,
        sort_order: 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (isEdit) patch(route('materials.update', material.id), options);
        else post(route('materials.store'), options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? t('common.edit') : t('materials.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <Field label={t('common.name')} htmlFor="m_name" error={errors.name}>
                        <Input id="m_name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('materials.unit')} htmlFor="m_unit" error={errors.unit}>
                            <Input id="m_unit" value={data.unit} onChange={(e) => setData('unit', e.target.value)} />
                        </Field>
                        <Field label={t('materials.reorder')} htmlFor="m_reorder" error={errors.reorder_level} optional>
                            <Input
                                id="m_reorder"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                value={data.reorder_level ?? ''}
                                onChange={(e) => setData('reorder_level', e.target.value)}
                            />
                        </Field>
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

function MovementDialog({
    open,
    onOpenChange,
    materials,
    direction,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    materials: MaterialRow[];
    direction: 'in' | 'out';
}) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors, reset } = useForm({
        raw_material_id: '' as number | '',
        movement_date: new Date().toISOString().slice(0, 10),
        direction,
        qty: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('materials.movements.store'), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{direction === 'in' ? t('materials.in') : t('materials.out')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <Field label={t('materials.title')} htmlFor="mv_material" error={errors.raw_material_id}>
                        <NativeSelect
                            id="mv_material"
                            value={data.raw_material_id === '' ? '' : String(data.raw_material_id)}
                            onChange={(e) => setData('raw_material_id', e.target.value === '' ? '' : Number(e.target.value))}
                        >
                            <option value="">—</option>
                            {materials.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name} ({m.unit})
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('orders.qty')} htmlFor="mv_qty" error={errors.qty}>
                            <Input
                                id="mv_qty"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                className="h-11 text-center text-lg"
                                value={data.qty}
                                onChange={(e) => setData('qty', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label={t('common.date')} htmlFor="mv_date" error={errors.movement_date}>
                            <Input
                                id="mv_date"
                                type="date"
                                dir="ltr"
                                value={data.movement_date}
                                onChange={(e) => setData('movement_date', e.target.value)}
                            />
                        </Field>
                    </div>
                    <Field label={t('common.notes')} htmlFor="mv_notes" error={errors.notes} optional>
                        <Input id="mv_notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !data.qty || data.raw_material_id === ''}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
