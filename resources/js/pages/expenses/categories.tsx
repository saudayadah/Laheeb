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
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, LoaderCircle, Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Category {
    id: number;
    name_ar: string;
    name_en: string | null;
    kind: string;
    active: boolean;
    sort_order: number;
}

interface Recurring {
    id: number;
    name: string;
    category: { id: number; name_ar: string } | null;
    amount: string;
    day_of_month: number;
    paid_from: string;
    supplier: IdName | null;
    active: boolean;
}

interface CategoriesPageProps {
    categories: Category[];
    recurring: Recurring[];
    suppliers: IdName[];
}

export default function ExpenseCategories({ categories, recurring, suppliers }: CategoriesPageProps) {
    const { t } = useTrans();
    const [catOpen, setCatOpen] = useState(false);
    const [editingCat, setEditingCat] = useState<Category | null>(null);
    const [recOpen, setRecOpen] = useState(false);
    const [editingRec, setEditingRec] = useState<Recurring | null>(null);

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('expenses.title'), href: '/expenses' },
                { title: t('expenses.categories'), href: '#' },
            ]}
        >
            <Head title={t('expenses.categories')} />
            <div className="flex flex-col gap-6 p-4">
                <PageHeader
                    title={t('expenses.categories')}
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('expenses.index')}>
                                <ArrowRight className="size-4" /> {t('common.back')}
                            </Link>
                        </Button>
                    }
                />

                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold">{t('expenses.category')}</h2>
                        <Button
                            size="sm"
                            onClick={() => {
                                setEditingCat(null);
                                setCatOpen(true);
                            }}
                        >
                            <Plus className="size-4" /> {t('common.create')}
                        </Button>
                    </div>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('products.name_ar')}</TableHead>
                                    <TableHead>{t('products.name_en')}</TableHead>
                                    <TableHead>
                                        {t('expenses.kind.operating')}/{t('expenses.kind.fixed')}
                                    </TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="w-14" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {categories.map((category) => (
                                    <TableRow key={category.id}>
                                        <TableCell className="font-medium">{category.name_ar}</TableCell>
                                        <TableCell className="text-muted-foreground">{category.name_en}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{t(`expenses.kind.${category.kind}`)}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <ActiveBadge active={category.active} />
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() => {
                                                    setEditingCat(category);
                                                    setCatOpen(true);
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
                </section>

                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="font-semibold">{t('expenses.recurring')}</h2>
                            <p className="text-muted-foreground text-xs">{t('expenses.recurring_hint')}</p>
                        </div>
                        <Button
                            size="sm"
                            onClick={() => {
                                setEditingRec(null);
                                setRecOpen(true);
                            }}
                        >
                            <Plus className="size-4" /> {t('common.create')}
                        </Button>
                    </div>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('expenses.recurring_name')}</TableHead>
                                    <TableHead>{t('expenses.category')}</TableHead>
                                    <TableHead className="text-end">{t('expenses.amount')}</TableHead>
                                    <TableHead className="text-center">{t('expenses.day_of_month')}</TableHead>
                                    <TableHead>{t('expenses.paid_from')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="w-14" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recurring.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">{row.name}</TableCell>
                                        <TableCell className="text-muted-foreground">{row.category?.name_ar}</TableCell>
                                        <TableCell className="text-end tabular-nums">{fmtAmount(row.amount)}</TableCell>
                                        <TableCell className="text-center tabular-nums">{row.day_of_month}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {t(`expenses.paid_from.${row.paid_from}`)}
                                                {row.supplier && <span className="ms-1">· {row.supplier.name}</span>}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <ActiveBadge active={row.active} />
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() => {
                                                    setEditingRec(row);
                                                    setRecOpen(true);
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
                </section>

                <CategoryDialog key={`cat-${editingCat?.id ?? 'new'}`} open={catOpen} onOpenChange={setCatOpen} category={editingCat} />
                <RecurringDialog
                    key={`rec-${editingRec?.id ?? 'new'}`}
                    open={recOpen}
                    onOpenChange={setRecOpen}
                    recurring={editingRec}
                    categories={categories}
                    suppliers={suppliers}
                />
            </div>
        </AppLayout>
    );
}

function CategoryDialog({ open, onOpenChange, category }: { open: boolean; onOpenChange: (o: boolean) => void; category: Category | null }) {
    const { t } = useTrans();
    const isEdit = category !== null;
    const { data, setData, post, patch, processing, errors } = useForm({
        name_ar: category?.name_ar ?? '',
        name_en: category?.name_en ?? '',
        kind: category?.kind ?? 'operating',
        active: category?.active ?? true,
        sort_order: category?.sort_order ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (isEdit) patch(route('expenses.categories.update', category.id), options);
        else post(route('expenses.categories.store'), options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? t('common.edit') : t('common.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('products.name_ar')} htmlFor="c_name_ar" error={errors.name_ar}>
                            <Input id="c_name_ar" value={data.name_ar} onChange={(e) => setData('name_ar', e.target.value)} required />
                        </Field>
                        <Field label={t('products.name_en')} htmlFor="c_name_en" error={errors.name_en} optional>
                            <Input id="c_name_en" dir="ltr" value={data.name_en ?? ''} onChange={(e) => setData('name_en', e.target.value)} />
                        </Field>
                        <Field label={t('expenses.category')} htmlFor="c_kind" error={errors.kind}>
                            <NativeSelect id="c_kind" value={data.kind} onChange={(e) => setData('kind', e.target.value)}>
                                <option value="operating">{t('expenses.kind.operating')}</option>
                                <option value="fixed">{t('expenses.kind.fixed')}</option>
                            </NativeSelect>
                        </Field>
                        <Field label={t('products.sort_order')} htmlFor="c_sort" error={errors.sort_order}>
                            <Input
                                id="c_sort"
                                type="number"
                                min="0"
                                dir="ltr"
                                value={String(data.sort_order)}
                                onChange={(e) => setData('sort_order', Number(e.target.value || 0))}
                            />
                        </Field>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox id="c_active" checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                        <label htmlFor="c_active" className="text-sm font-medium">
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

function RecurringDialog({
    open,
    onOpenChange,
    recurring,
    categories,
    suppliers,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    recurring: Recurring | null;
    categories: Category[];
    suppliers: IdName[];
}) {
    const { t } = useTrans();
    const isEdit = recurring !== null;
    const { data, setData, post, patch, processing, errors } = useForm({
        name: recurring?.name ?? '',
        expense_category_id: recurring?.category?.id ?? ('' as number | ''),
        amount: recurring?.amount ?? '',
        day_of_month: recurring?.day_of_month ?? 1,
        paid_from: recurring?.paid_from ?? 'bank',
        supplier_id: recurring?.supplier?.id ?? ('' as number | ''),
        active: recurring?.active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
        if (isEdit) patch(route('expenses.recurring.update', recurring.id), options);
        else post(route('expenses.recurring.store'), options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('expenses.recurring')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <Field label={t('expenses.recurring_name')} htmlFor="r_name" error={errors.name}>
                        <Input id="r_name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('expenses.category')} htmlFor="r_cat" error={errors.expense_category_id}>
                            <NativeSelect
                                id="r_cat"
                                value={data.expense_category_id === '' ? '' : String(data.expense_category_id)}
                                onChange={(e) => setData('expense_category_id', e.target.value === '' ? '' : Number(e.target.value))}
                            >
                                <option value="">—</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name_ar}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label={t('expenses.amount')} htmlFor="r_amount" error={errors.amount}>
                            <Input
                                id="r_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label={t('expenses.day_of_month')} htmlFor="r_day" error={errors.day_of_month}>
                            <Input
                                id="r_day"
                                type="number"
                                min="1"
                                max="28"
                                dir="ltr"
                                value={String(data.day_of_month)}
                                onChange={(e) => setData('day_of_month', Number(e.target.value || 1))}
                            />
                        </Field>
                        <Field label={t('expenses.paid_from')} htmlFor="r_source" error={errors.paid_from}>
                            <NativeSelect id="r_source" value={data.paid_from} onChange={(e) => setData('paid_from', e.target.value)}>
                                {['bank', 'counter_cash', 'supplier_credit'].map((s) => (
                                    <option key={s} value={s}>
                                        {t(`expenses.paid_from.${s}`)}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        {data.paid_from === 'supplier_credit' && (
                            <Field label={t('nav.suppliers')} htmlFor="r_supplier" error={errors.supplier_id}>
                                <NativeSelect
                                    id="r_supplier"
                                    value={data.supplier_id === '' ? '' : String(data.supplier_id)}
                                    onChange={(e) => setData('supplier_id', e.target.value === '' ? '' : Number(e.target.value))}
                                >
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
                    <div className="flex items-center gap-2">
                        <Checkbox id="r_active" checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                        <label htmlFor="r_active" className="text-sm font-medium">
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
