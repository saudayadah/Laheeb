import { EmptyState } from '@/components/empty-state';
import { Field, NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { ActiveBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { useTrans } from '@/lib/i18n';
import { type UserItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus, UsersRound } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface UsersPageProps {
    users: UserItem[];
    roles: string[];
    currentUserId: number;
}

export default function UsersIndex({ users, roles, currentUserId }: UsersPageProps) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<UserItem | null>(null);

    return (
        <AppLayout breadcrumbs={[{ title: t('users.title'), href: '/users' }]}>
            <Head title={t('users.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('users.title')}
                    actions={
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setOpen(true);
                            }}
                        >
                            <Plus className="size-4" />
                            {t('users.create')}
                        </Button>
                    }
                />

                {users.length === 0 ? (
                    <EmptyState icon={UsersRound} message={t('users.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('users.email')}</TableHead>
                                    <TableHead>{t('users.phone')}</TableHead>
                                    <TableHead>{t('users.role')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="w-14 text-end">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">{user.name}</TableCell>
                                        <TableCell className="text-muted-foreground" dir="ltr">
                                            {user.email}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {user.phone ?? '—'}
                                        </TableCell>
                                        <TableCell>{user.role ? <Badge variant="outline">{t(`users.role.${user.role}`)}</Badge> : '—'}</TableCell>
                                        <TableCell>
                                            <ActiveBadge active={user.active} />
                                        </TableCell>
                                        <TableCell className="text-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() => {
                                                    setEditing(user);
                                                    setOpen(true);
                                                }}
                                            >
                                                <Pencil className="size-4" />
                                                <span className="sr-only">{t('common.edit')}</span>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <UserDialog
                    key={`${editing?.id ?? 'new'}-${open}`}
                    open={open}
                    onOpenChange={setOpen}
                    user={editing}
                    roles={roles}
                    isSelf={editing?.id === currentUserId}
                />
            </div>
        </AppLayout>
    );
}

function UserDialog({
    open,
    onOpenChange,
    user,
    roles,
    isSelf,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: UserItem | null;
    roles: string[];
    isSelf: boolean;
}) {
    const { t } = useTrans();
    const isEdit = user !== null;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        role: user?.role ?? 'driver',
        password: '',
        active: user?.active ?? true,
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
            put(route('users.update', user.id), options);
        } else {
            post(route('users.store'), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? t('users.edit') : t('users.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label={t('common.name')} htmlFor="u_name" error={errors.name}>
                        <Input id="u_name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('users.email')} htmlFor="u_email" error={errors.email}>
                            <Input
                                id="u_email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                dir="ltr"
                            />
                        </Field>
                        <Field label={t('users.phone')} htmlFor="u_phone" error={errors.phone} optional>
                            <Input
                                id="u_phone"
                                value={data.phone ?? ''}
                                onChange={(e) => setData('phone', e.target.value)}
                                dir="ltr"
                                inputMode="tel"
                            />
                        </Field>
                        <Field label={t('users.role')} htmlFor="u_role" error={errors.role}>
                            <NativeSelect
                                id="u_role"
                                value={data.role ?? 'driver'}
                                onChange={(e) => setData('role', e.target.value)}
                                disabled={isSelf}
                            >
                                {roles.map((r) => (
                                    <option key={r} value={r}>
                                        {t(`users.role.${r}`)}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label={t('users.password')} htmlFor="u_password" error={errors.password}>
                            <Input
                                id="u_password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                dir="ltr"
                                placeholder={isEdit ? t('users.password_hint') : ''}
                                required={!isEdit}
                            />
                        </Field>
                    </div>
                    {!isSelf && (
                        <div className="flex items-center gap-2">
                            <Checkbox id="u_active" checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                            <label htmlFor="u_active" className="text-sm font-medium">
                                {t('common.active')}
                            </label>
                        </div>
                    )}
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
