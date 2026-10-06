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
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type IdName } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Contact, LoaderCircle, Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface EmployeeRow {
    id: number;
    name_ar: string;
    name_en: string | null;
    nationality: string | null;
    iqama_number: string | null;
    iqama_expiry: string | null;
    iqama_expiring: boolean;
    job: string;
    basic_salary: string;
    join_date: string | null;
    active: boolean;
    user: IdName | null;
    outstanding: string;
}

interface EmployeesPageProps {
    employees: EmployeeRow[];
    users: IdName[];
    totalSalaries: string;
    totalOutstanding: string;
}

export default function EmployeesIndex({ employees, users, totalSalaries, totalOutstanding }: EmployeesPageProps) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<EmployeeRow | null>(null);

    return (
        <AppLayout breadcrumbs={[{ title: t('employees.title'), href: '/employees' }]}>
            <Head title={t('employees.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('employees.title')}
                    actions={
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setOpen(true);
                            }}
                        >
                            <Plus className="size-4" /> {t('employees.create')}
                        </Button>
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:max-w-lg">
                    <div className="rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(totalSalaries)}</div>
                        <div className="text-muted-foreground text-xs">{t('employees.total_salaries')}</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div
                            className={`text-2xl font-bold tabular-nums ${parseFloat(totalOutstanding) > 0 ? 'text-amber-600 dark:text-amber-400' : ''}`}
                        >
                            {fmtAmount(totalOutstanding)}
                        </div>
                        <div className="text-muted-foreground text-xs">{t('employees.total_outstanding')}</div>
                    </div>
                </div>

                {employees.length === 0 ? (
                    <EmptyState icon={Contact} message={t('employees.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('employees.job')}</TableHead>
                                    <TableHead className="text-end">{t('employees.basic_salary')}</TableHead>
                                    <TableHead className="text-end">{t('employees.outstanding')}</TableHead>
                                    <TableHead>{t('employees.iqama_expiry')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="w-14" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {employees.map((employee) => (
                                    <TableRow key={employee.id}>
                                        <TableCell>
                                            <div className="font-medium">{employee.name_ar}</div>
                                            {employee.nationality && <div className="text-muted-foreground text-xs">{employee.nationality}</div>}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{t(`employees.job.${employee.job}`)}</Badge>
                                        </TableCell>
                                        <TableCell className="text-end tabular-nums">{fmtAmount(employee.basic_salary)}</TableCell>
                                        <TableCell
                                            className={`text-end tabular-nums ${parseFloat(employee.outstanding) > 0 ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-muted-foreground'}`}
                                        >
                                            {fmtAmount(employee.outstanding)}
                                        </TableCell>
                                        <TableCell>
                                            <span className="tabular-nums" dir="ltr">
                                                {employee.iqama_expiry ?? '—'}
                                            </span>
                                            {employee.iqama_expiring && (
                                                <Badge variant="outline" className="ms-2 border-red-300 text-[10px] text-red-700 dark:text-red-400">
                                                    {t('employees.iqama_expiring')}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <ActiveBadge active={employee.active} />
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() => {
                                                    setEditing(employee);
                                                    setOpen(true);
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

                <EmployeeDialog key={editing?.id ?? 'new'} open={open} onOpenChange={setOpen} employee={editing} users={users} />
            </div>
        </AppLayout>
    );
}

function EmployeeDialog({
    open,
    onOpenChange,
    employee,
    users,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    employee: EmployeeRow | null;
    users: IdName[];
}) {
    const { t } = useTrans();
    const isEdit = employee !== null;
    const { data, setData, post, patch, processing, errors } = useForm({
        name_ar: employee?.name_ar ?? '',
        name_en: employee?.name_en ?? '',
        nationality: employee?.nationality ?? '',
        iqama_number: '',
        iqama_expiry: employee?.iqama_expiry ?? '',
        job: employee?.job ?? 'worker',
        basic_salary: employee?.basic_salary ?? '',
        join_date: employee?.join_date ?? '',
        active: employee?.active ?? true,
        user_id: employee?.user?.id ?? ('' as number | ''),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (isEdit) patch(route('employees.update', employee.id), options);
        else post(route('employees.store'), options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{isEdit ? t('employees.edit') : t('employees.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('products.name_ar')} htmlFor="emp_name" error={errors.name_ar}>
                            <Input id="emp_name" value={data.name_ar} onChange={(e) => setData('name_ar', e.target.value)} required />
                        </Field>
                        <Field label={t('products.name_en')} htmlFor="emp_name_en" error={errors.name_en} optional>
                            <Input id="emp_name_en" dir="ltr" value={data.name_en ?? ''} onChange={(e) => setData('name_en', e.target.value)} />
                        </Field>
                        <Field label={t('employees.nationality')} htmlFor="emp_nat" error={errors.nationality} optional>
                            <Input id="emp_nat" value={data.nationality ?? ''} onChange={(e) => setData('nationality', e.target.value)} />
                        </Field>
                        <Field label={t('employees.job')} htmlFor="emp_job" error={errors.job}>
                            <NativeSelect id="emp_job" value={data.job} onChange={(e) => setData('job', e.target.value)}>
                                {['baker', 'driver', 'worker', 'other'].map((j) => (
                                    <option key={j} value={j}>
                                        {t(`employees.job.${j}`)}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label={t('employees.iqama')} htmlFor="emp_iqama" error={errors.iqama_number} optional>
                            <Input
                                id="emp_iqama"
                                dir="ltr"
                                value={data.iqama_number}
                                onChange={(e) => setData('iqama_number', e.target.value)}
                                placeholder={isEdit ? t('employees.iqama_keep') : ''}
                            />
                        </Field>
                        <Field label={t('employees.iqama_expiry')} htmlFor="emp_iqama_exp" error={errors.iqama_expiry} optional>
                            <Input
                                id="emp_iqama_exp"
                                type="date"
                                dir="ltr"
                                value={data.iqama_expiry ?? ''}
                                onChange={(e) => setData('iqama_expiry', e.target.value)}
                            />
                        </Field>
                        <Field label={t('employees.basic_salary')} htmlFor="emp_salary" error={errors.basic_salary}>
                            <Input
                                id="emp_salary"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                value={data.basic_salary}
                                onChange={(e) => setData('basic_salary', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label={t('employees.join_date')} htmlFor="emp_join" error={errors.join_date} optional>
                            <Input
                                id="emp_join"
                                type="date"
                                dir="ltr"
                                value={data.join_date ?? ''}
                                onChange={(e) => setData('join_date', e.target.value)}
                            />
                        </Field>
                        <Field label={t('employees.linked_user')} htmlFor="emp_user" error={errors.user_id} optional>
                            <NativeSelect
                                id="emp_user"
                                value={data.user_id === '' ? '' : String(data.user_id)}
                                onChange={(e) => setData('user_id', e.target.value === '' ? '' : Number(e.target.value))}
                            >
                                <option value="">{t('employees.no_user')}</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <div className="flex items-center gap-2 pt-6">
                            <Checkbox id="emp_active" checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                            <label htmlFor="emp_active" className="text-sm font-medium">
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
