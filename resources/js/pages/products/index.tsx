import { ConfirmDelete } from '@/components/confirm-delete';
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
import { fmtPrice } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type ProductItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus, Wheat } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface ProductsPageProps {
    products: ProductItem[];
    categories: string[];
    canManage: boolean;
}

export default function ProductsIndex({ products, categories, canManage }: ProductsPageProps) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<ProductItem | null>(null);

    return (
        <AppLayout breadcrumbs={[{ title: t('products.title'), href: '/products' }]}>
            <Head title={t('products.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('products.title')}
                    actions={
                        canManage && (
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setOpen(true);
                                }}
                            >
                                <Plus className="size-4" />
                                {t('products.create')}
                            </Button>
                        )
                    }
                />

                {products.length === 0 ? (
                    <EmptyState icon={Wheat} message={t('products.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('products.name_ar')}</TableHead>
                                    <TableHead>{t('products.category')}</TableHead>
                                    <TableHead className="text-end">{t('products.size_cm')}</TableHead>
                                    <TableHead className="text-end">{t('products.default_price')}</TableHead>
                                    <TableHead className="text-end">{t('products.vat_rate')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    {canManage && <TableHead className="w-20 text-end">{t('common.actions')}</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {products.map((product) => (
                                    <TableRow key={product.id}>
                                        <TableCell className="font-medium">{product.name_ar}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{t(`products.category.${product.category}`)}</Badge>
                                        </TableCell>
                                        <TableCell className="text-end tabular-nums">{product.size_cm ?? '—'}</TableCell>
                                        <TableCell className="text-end tabular-nums">{fmtPrice(product.default_price)}</TableCell>
                                        <TableCell className="text-muted-foreground text-end tabular-nums">
                                            {product.vat_rate ?? t('products.vat_default')}
                                        </TableCell>
                                        <TableCell>
                                            <ActiveBadge active={product.active} />
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-end">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        onClick={() => {
                                                            setEditing(product);
                                                            setOpen(true);
                                                        }}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <ConfirmDelete url={route('products.destroy', product.id)} />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <ProductDialog key={editing?.id ?? 'new'} open={open} onOpenChange={setOpen} product={editing} categories={categories} />
            </div>
        </AppLayout>
    );
}

function ProductDialog({
    open,
    onOpenChange,
    product,
    categories,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    product: ProductItem | null;
    categories: string[];
}) {
    const { t } = useTrans();
    const isEdit = product !== null;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name_ar: product?.name_ar ?? '',
        name_en: product?.name_en ?? '',
        category: product?.category ?? 'saj',
        size_cm: product?.size_cm ?? ('' as number | ''),
        unit: product?.unit ?? 'loaf',
        default_price: product?.default_price ?? '',
        vat_rate: product?.vat_rate ?? '',
        sort_order: product?.sort_order ?? 0,
        active: product?.active ?? true,
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
            put(route('products.update', product.id), options);
        } else {
            post(route('products.store'), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? t('products.edit') : t('products.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('products.name_ar')} htmlFor="p_name_ar" error={errors.name_ar}>
                            <Input id="p_name_ar" value={data.name_ar} onChange={(e) => setData('name_ar', e.target.value)} required />
                        </Field>
                        <Field label={t('products.name_en')} htmlFor="p_name_en" error={errors.name_en} optional>
                            <Input id="p_name_en" value={data.name_en ?? ''} onChange={(e) => setData('name_en', e.target.value)} dir="ltr" />
                        </Field>
                        <Field label={t('products.category')} htmlFor="p_category" error={errors.category}>
                            <NativeSelect id="p_category" value={data.category} onChange={(e) => setData('category', e.target.value)}>
                                {categories.map((c) => (
                                    <option key={c} value={c}>
                                        {t(`products.category.${c}`)}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label={t('products.size_cm')} htmlFor="p_size" error={errors.size_cm} optional>
                            <Input
                                id="p_size"
                                type="number"
                                min="1"
                                max="200"
                                inputMode="numeric"
                                value={data.size_cm === '' ? '' : String(data.size_cm)}
                                onChange={(e) => setData('size_cm', e.target.value === '' ? '' : Number(e.target.value))}
                                dir="ltr"
                            />
                        </Field>
                        <Field label={t('products.default_price')} htmlFor="p_price" error={errors.default_price}>
                            <Input
                                id="p_price"
                                type="number"
                                step="0.0001"
                                min="0"
                                inputMode="decimal"
                                value={data.default_price}
                                onChange={(e) => setData('default_price', e.target.value)}
                                required
                                dir="ltr"
                            />
                        </Field>
                        <Field label={t('products.vat_rate')} htmlFor="p_vat" error={errors.vat_rate} optional>
                            <Input
                                id="p_vat"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                inputMode="decimal"
                                value={data.vat_rate ?? ''}
                                onChange={(e) => setData('vat_rate', e.target.value)}
                                placeholder={t('products.vat_default')}
                                dir="ltr"
                            />
                        </Field>
                        <Field label={t('products.sort_order')} htmlFor="p_sort" error={errors.sort_order}>
                            <Input
                                id="p_sort"
                                type="number"
                                min="0"
                                inputMode="numeric"
                                value={String(data.sort_order)}
                                onChange={(e) => setData('sort_order', Number(e.target.value || 0))}
                                dir="ltr"
                            />
                        </Field>
                        <div className="flex items-center gap-2 pt-6">
                            <Checkbox id="p_active" checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                            <label htmlFor="p_active" className="text-sm font-medium">
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
