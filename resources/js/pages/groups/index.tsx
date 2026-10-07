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
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type GroupItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FolderTree, LoaderCircle, Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface GroupsPageProps {
    groups: GroupItem[];
    canManage: boolean;
}

export default function GroupsIndex({ groups, canManage }: GroupsPageProps) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<GroupItem | null>(null);

    const openCreate = () => {
        setEditing(null);
        setOpen(true);
    };

    const openEdit = (group: GroupItem) => {
        setEditing(group);
        setOpen(true);
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('groups.title'), href: '/groups' }]}>
            <Head title={t('groups.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('groups.title')}
                    actions={
                        canManage && (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                {t('groups.create')}
                            </Button>
                        )
                    }
                />

                {groups.length === 0 ? (
                    <EmptyState icon={FolderTree} message={t('groups.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead className="text-end">{t('groups.credit_limit')}</TableHead>
                                    <TableHead>{t('groups.credit_scope')}</TableHead>
                                    <TableHead className="text-end">{t('groups.branches')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    {canManage && <TableHead className="w-20 text-end">{t('common.actions')}</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {groups.map((group) => (
                                    <TableRow key={group.id}>
                                        <TableCell className="font-medium">{group.name}</TableCell>
                                        <TableCell className="text-end tabular-nums">{fmtAmount(group.credit_limit)}</TableCell>
                                        <TableCell className="text-muted-foreground">{t(`groups.scope.${group.credit_scope}`)}</TableCell>
                                        <TableCell className="text-end tabular-nums">{group.customers_count}</TableCell>
                                        <TableCell>
                                            <ActiveBadge active={group.active} />
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-end">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" className="size-8" onClick={() => openEdit(group)}>
                                                        <Pencil className="size-4" />
                                                        <span className="sr-only">{t('common.edit')}</span>
                                                    </Button>
                                                    <ConfirmDelete url={route('groups.destroy', group.id)} />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <GroupDialog key={editing?.id ?? 'new'} open={open} onOpenChange={setOpen} group={editing} />
            </div>
        </AppLayout>
    );
}

function GroupDialog({ open, onOpenChange, group }: { open: boolean; onOpenChange: (open: boolean) => void; group: GroupItem | null }) {
    const { t } = useTrans();
    const isEdit = group !== null;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: group?.name ?? '',
        credit_limit: group?.credit_limit ?? '',
        credit_scope: group?.credit_scope ?? 'group',
        active: group?.active ?? true,
        notes: group?.notes ?? '',
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
            put(route('groups.update', group.id), options);
        } else {
            post(route('groups.store'), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? t('groups.edit') : t('groups.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label={t('common.name')} htmlFor="g_name" error={errors.name}>
                        <Input id="g_name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('groups.credit_limit')} htmlFor="g_limit" error={errors.credit_limit} optional>
                            <Input
                                id="g_limit"
                                type="number"
                                step="0.01"
                                min="0"
                                inputMode="decimal"
                                value={data.credit_limit ?? ''}
                                onChange={(e) => setData('credit_limit', e.target.value)}
                                dir="ltr"
                            />
                        </Field>
                        <Field label={t('groups.credit_scope')} htmlFor="g_scope" error={errors.credit_scope}>
                            <NativeSelect id="g_scope" value={data.credit_scope} onChange={(e) => setData('credit_scope', e.target.value)}>
                                <option value="group">{t('groups.scope.group')}</option>
                                <option value="branch">{t('groups.scope.branch')}</option>
                            </NativeSelect>
                        </Field>
                    </div>
                    <Field label={t('common.notes')} htmlFor="g_notes" error={errors.notes} optional>
                        <Input id="g_notes" value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                    <div className="flex items-center gap-2">
                        <Checkbox id="g_active" checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                        <label htmlFor="g_active" className="text-sm font-medium">
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
