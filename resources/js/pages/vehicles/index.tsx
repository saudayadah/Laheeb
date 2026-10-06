import { EmptyState } from '@/components/empty-state';
import { Field, NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { ActiveBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtInt } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { Car, LoaderCircle, Pencil, Plus, Wrench } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface VehicleRow {
    id: number;
    name: string;
    plate: string | null;
    active: boolean;
    total_cost: string;
    last_service: string | null;
    next_due_date: string | null;
    due_soon: boolean;
}

interface MaintenanceRow {
    id: number;
    vehicle: { id: number; name: string; plate: string | null } | null;
    date: string;
    task: string;
    odometer: number | null;
    next_due_date: string | null;
    next_due_odometer: number | null;
    due_soon: boolean;
    notes: string | null;
}

interface VehiclesPageProps {
    vehicles: VehicleRow[];
    maintenances: MaintenanceRow[];
}

export default function VehiclesIndex({ vehicles, maintenances }: VehiclesPageProps) {
    const { t } = useTrans();
    const [vehicleOpen, setVehicleOpen] = useState(false);
    const [editing, setEditing] = useState<VehicleRow | null>(null);
    const [maintOpen, setMaintOpen] = useState(false);

    return (
        <AppLayout breadcrumbs={[{ title: t('vehicles.title'), href: '/vehicles' }]}>
            <Head title={t('vehicles.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('vehicles.title')}
                    description={t('vehicles.cost_hint')}
                    actions={
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={() => setMaintOpen(true)} disabled={vehicles.length === 0}>
                                <Wrench className="size-4" /> {t('vehicles.maintenance_new')}
                            </Button>
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setVehicleOpen(true);
                                }}
                            >
                                <Plus className="size-4" /> {t('vehicles.create')}
                            </Button>
                        </div>
                    }
                />

                {vehicles.length === 0 ? (
                    <EmptyState icon={Car} message={t('vehicles.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('vehicles.plate')}</TableHead>
                                    <TableHead className="text-end">{t('vehicles.total_cost')}</TableHead>
                                    <TableHead>{t('vehicles.last_service')}</TableHead>
                                    <TableHead>{t('vehicles.next_due')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="w-14" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {vehicles.map((vehicle) => (
                                    <TableRow key={vehicle.id}>
                                        <TableCell className="font-medium">{vehicle.name}</TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {vehicle.plate ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-end font-semibold tabular-nums">{fmtAmount(vehicle.total_cost)}</TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {vehicle.last_service ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <span className="tabular-nums" dir="ltr">
                                                {vehicle.next_due_date ?? '—'}
                                            </span>
                                            {vehicle.due_soon && (
                                                <Badge variant="outline" className="ms-2 border-red-300 text-[10px] text-red-700 dark:text-red-400">
                                                    {t('vehicles.due_soon')}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <ActiveBadge active={vehicle.active} />
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() => {
                                                    setEditing(vehicle);
                                                    setVehicleOpen(true);
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
                    <h2 className="font-semibold">{t('vehicles.history')}</h2>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.date')}</TableHead>
                                    <TableHead>{t('vehicles.title')}</TableHead>
                                    <TableHead>{t('vehicles.task')}</TableHead>
                                    <TableHead className="text-end">{t('vehicles.odometer')}</TableHead>
                                    <TableHead>{t('vehicles.next_due')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {maintenances.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {m.date}
                                        </TableCell>
                                        <TableCell className="font-medium">{m.vehicle?.name}</TableCell>
                                        <TableCell>{m.task}</TableCell>
                                        <TableCell className="text-end tabular-nums">{m.odometer ? fmtInt(m.odometer) : '—'}</TableCell>
                                        <TableCell>
                                            <span className="tabular-nums" dir="ltr">
                                                {m.next_due_date ?? '—'}
                                            </span>
                                            {m.next_due_odometer != null && (
                                                <span className="text-muted-foreground ms-1 text-xs tabular-nums">
                                                    / {fmtInt(m.next_due_odometer)} كم
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <VehicleDialog key={editing?.id ?? 'new'} open={vehicleOpen} onOpenChange={setVehicleOpen} vehicle={editing} />
                <MaintenanceDialog open={maintOpen} onOpenChange={setMaintOpen} vehicles={vehicles} />
            </div>
        </AppLayout>
    );
}

function VehicleDialog({ open, onOpenChange, vehicle }: { open: boolean; onOpenChange: (o: boolean) => void; vehicle: VehicleRow | null }) {
    const { t } = useTrans();
    const isEdit = vehicle !== null;
    const { data, setData, post, patch, processing, errors } = useForm({
        name: vehicle?.name ?? '',
        plate: vehicle?.plate ?? '',
        active: vehicle?.active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (isEdit) patch(route('vehicles.update', vehicle.id), options);
        else post(route('vehicles.store'), options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? t('common.edit') : t('vehicles.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('common.name')} htmlFor="v_name" error={errors.name}>
                            <Input id="v_name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        </Field>
                        <Field label={t('vehicles.plate')} htmlFor="v_plate" error={errors.plate} optional>
                            <Input id="v_plate" dir="ltr" value={data.plate ?? ''} onChange={(e) => setData('plate', e.target.value)} />
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

function MaintenanceDialog({ open, onOpenChange, vehicles }: { open: boolean; onOpenChange: (o: boolean) => void; vehicles: VehicleRow[] }) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors, reset } = useForm({
        vehicle_id: '' as number | '',
        service_date: new Date().toISOString().slice(0, 10),
        task: '',
        odometer: '',
        next_due_date: '',
        next_due_odometer: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('vehicles.maintenance.store'), {
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
                    <DialogTitle>{t('vehicles.maintenance_new')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('vehicles.title')} htmlFor="mt_vehicle" error={errors.vehicle_id}>
                            <NativeSelect
                                id="mt_vehicle"
                                value={data.vehicle_id === '' ? '' : String(data.vehicle_id)}
                                onChange={(e) => setData('vehicle_id', e.target.value === '' ? '' : Number(e.target.value))}
                            >
                                <option value="">—</option>
                                {vehicles.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label={t('common.date')} htmlFor="mt_date" error={errors.service_date}>
                            <Input
                                id="mt_date"
                                type="date"
                                dir="ltr"
                                value={data.service_date}
                                onChange={(e) => setData('service_date', e.target.value)}
                            />
                        </Field>
                    </div>
                    <Field label={t('vehicles.task')} htmlFor="mt_task" error={errors.task}>
                        <Input
                            id="mt_task"
                            value={data.task}
                            onChange={(e) => setData('task', e.target.value)}
                            placeholder="تغيير زيت، فحص فرامل..."
                            required
                        />
                    </Field>
                    <div className="grid grid-cols-3 gap-3">
                        <Field label={t('vehicles.odometer')} htmlFor="mt_odo" error={errors.odometer} optional>
                            <Input
                                id="mt_odo"
                                type="number"
                                min="0"
                                dir="ltr"
                                value={data.odometer}
                                onChange={(e) => setData('odometer', e.target.value)}
                            />
                        </Field>
                        <Field label={t('vehicles.next_due')} htmlFor="mt_due" error={errors.next_due_date} optional>
                            <Input
                                id="mt_due"
                                type="date"
                                dir="ltr"
                                value={data.next_due_date}
                                onChange={(e) => setData('next_due_date', e.target.value)}
                            />
                        </Field>
                        <Field label={t('vehicles.next_due_odometer')} htmlFor="mt_due_odo" error={errors.next_due_odometer} optional>
                            <Input
                                id="mt_due_odo"
                                type="number"
                                min="0"
                                dir="ltr"
                                value={data.next_due_odometer}
                                onChange={(e) => setData('next_due_odometer', e.target.value)}
                            />
                        </Field>
                    </div>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || !data.task || data.vehicle_id === ''}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
