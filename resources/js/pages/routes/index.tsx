import { ConfirmDelete } from '@/components/confirm-delete';
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
import { useTrans } from '@/lib/i18n';
import { type IdName, type RouteItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, MapPinned, Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface RoutesPageProps {
    routes: RouteItem[];
    drivers: IdName[];
    canManage: boolean;
}

export default function RoutesIndex({ routes, drivers, canManage }: RoutesPageProps) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<RouteItem | null>(null);

    return (
        <AppLayout breadcrumbs={[{ title: t('routes.title'), href: '/delivery-routes' }]}>
            <Head title={t('routes.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('routes.title')}
                    actions={
                        canManage && (
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setOpen(true);
                                }}
                            >
                                <Plus className="size-4" />
                                {t('routes.create')}
                            </Button>
                        )
                    }
                />

                {routes.length === 0 ? (
                    <EmptyState icon={MapPinned} message={t('routes.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('routes.city')}</TableHead>
                                    <TableHead>{t('routes.default_driver')}</TableHead>
                                    <TableHead className="text-end">{t('routes.customers_count')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    {canManage && <TableHead className="w-20 text-end">{t('common.actions')}</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {routes.map((routeItem) => (
                                    <TableRow key={routeItem.id}>
                                        <TableCell className="font-medium">{routeItem.name}</TableCell>
                                        <TableCell className="text-muted-foreground">{routeItem.city ?? '—'}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {routeItem.default_driver?.name ?? t('routes.no_driver')}
                                        </TableCell>
                                        <TableCell className="text-end tabular-nums">{routeItem.customers_count}</TableCell>
                                        <TableCell>
                                            <ActiveBadge active={routeItem.active} />
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-end">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        onClick={() => {
                                                            setEditing(routeItem);
                                                            setOpen(true);
                                                        }}
                                                    >
                                                        <Pencil className="size-4" />
                                                        <span className="sr-only">{t('common.edit')}</span>
                                                    </Button>
                                                    <ConfirmDelete url={route('delivery-routes.destroy', routeItem.id)} />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <RouteDialog key={`${editing?.id ?? 'new'}-${open}`} open={open} onOpenChange={setOpen} routeItem={editing} drivers={drivers} />
            </div>
        </AppLayout>
    );
}

function RouteDialog({
    open,
    onOpenChange,
    routeItem,
    drivers,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    routeItem: RouteItem | null;
    drivers: IdName[];
}) {
    const { t } = useTrans();
    const isEdit = routeItem !== null;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: routeItem?.name ?? '',
        city: routeItem?.city ?? '',
        default_driver_id: routeItem?.default_driver?.id ?? ('' as number | ''),
        sort_order: routeItem?.sort_order ?? 0,
        active: routeItem?.active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        };
        if (isEdit) {
            put(route('delivery-routes.update', routeItem.id), options);
        } else {
            post(route('delivery-routes.store'), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? t('routes.edit') : t('routes.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label={t('common.name')} htmlFor="r_name" error={errors.name}>
                        <Input id="r_name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('routes.city')} htmlFor="r_city" error={errors.city} optional>
                            <Input id="r_city" value={data.city ?? ''} onChange={(e) => setData('city', e.target.value)} />
                        </Field>
                        <Field label={t('routes.default_driver')} htmlFor="r_driver" error={errors.default_driver_id}>
                            <NativeSelect
                                id="r_driver"
                                value={data.default_driver_id === '' ? '' : String(data.default_driver_id)}
                                onChange={(e) => setData('default_driver_id', e.target.value === '' ? '' : Number(e.target.value))}
                            >
                                <option value="">{t('routes.no_driver')}</option>
                                {drivers.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label={t('routes.sort_order')} htmlFor="r_sort" error={errors.sort_order}>
                            <Input
                                id="r_sort"
                                type="number"
                                min="0"
                                inputMode="numeric"
                                value={String(data.sort_order)}
                                onChange={(e) => setData('sort_order', Number(e.target.value || 0))}
                                dir="ltr"
                            />
                        </Field>
                        <div className="flex items-center gap-2 pt-6">
                            <Checkbox id="r_active" checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                            <label htmlFor="r_active" className="text-sm font-medium">
                                {t('common.active')}
                            </label>
                        </div>
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
