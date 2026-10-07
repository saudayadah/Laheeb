import { ConfirmDelete } from '@/components/confirm-delete';
import { Field, NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtPrice } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type CustomerFormData, type IdName, type PriceRow } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface ProductOption {
    id: number;
    name_ar: string;
    name_en: string | null;
}

interface CustomerFormProps {
    customer: CustomerFormData | null;
    prices: PriceRow[];
    suggestedCode: string | null;
    groups: IdName[];
    routes: IdName[];
    products: ProductOption[];
    canManagePrices: boolean;
    canViewPrices: boolean;
}

export default function CustomerForm({
    customer,
    prices,
    suggestedCode,
    groups,
    routes,
    products,
    canManagePrices,
    canViewPrices,
}: CustomerFormProps) {
    const { t } = useTrans();
    const isEdit = customer !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        code: customer?.code ?? '',
        name: customer?.name ?? '',
        name_en: customer?.name_en ?? '',
        customer_group_id: customer?.customer_group_id ?? ('' as number | ''),
        type: customer?.type ?? 'wholesale',
        payment_term: customer?.payment_term ?? 'cash',
        credit_limit: customer?.credit_limit ?? '',
        credit_days: customer?.credit_days ?? ('' as number | ''),
        delivery_route_id: customer?.delivery_route_id ?? ('' as number | ''),
        stop_sequence: customer?.stop_sequence ?? 0,
        city: customer?.city ?? '',
        phone: customer?.phone ?? '',
        whatsapp: customer?.whatsapp ?? '',
        map_url: customer?.map_url ?? '',
        vat_number: customer?.vat_number ?? '',
        cr_number: customer?.cr_number ?? '',
        national_address: customer?.national_address ?? '',
        active: customer?.active ?? true,
        notes: customer?.notes ?? '',
        default_price: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('customers.update', customer.id));
        } else {
            post(route('customers.store'));
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('customers.title'), href: '/customers' },
                { title: isEdit ? customer.name : t('customers.create'), href: '#' },
            ]}
        >
            <Head title={isEdit ? t('customers.edit') : t('customers.create')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={isEdit ? customer.name : t('customers.create')} />

                <form onSubmit={submit} className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('customers.section.basic')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('customers.code')} htmlFor="code" error={errors.code} optional>
                                <Input
                                    id="code"
                                    value={data.code ?? ''}
                                    onChange={(e) => setData('code', e.target.value)}
                                    placeholder={suggestedCode ?? ''}
                                    dir="ltr"
                                />
                            </Field>
                            <Field label={t('customers.name')} htmlFor="name" error={errors.name}>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            </Field>
                            <Field label={t('customers.name_en')} htmlFor="name_en" error={errors.name_en} optional>
                                <Input id="name_en" value={data.name_en ?? ''} onChange={(e) => setData('name_en', e.target.value)} />
                            </Field>
                            <Field label={t('customers.group')} htmlFor="group" error={errors.customer_group_id}>
                                <NativeSelect
                                    id="group"
                                    value={data.customer_group_id === '' ? '' : String(data.customer_group_id)}
                                    onChange={(e) => setData('customer_group_id', e.target.value === '' ? '' : Number(e.target.value))}
                                >
                                    <option value="">{t('customers.no_group')}</option>
                                    {groups.map((g) => (
                                        <option key={g.id} value={g.id}>
                                            {g.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label={t('customers.type')} htmlFor="type" error={errors.type}>
                                <NativeSelect id="type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                    <option value="wholesale">{t('customers.type.wholesale')}</option>
                                    <option value="retail">{t('customers.type.retail')}</option>
                                    <option value="walkin">{t('customers.type.walkin')}</option>
                                </NativeSelect>
                            </Field>
                            <div className="flex items-center gap-2 pt-6">
                                <Checkbox id="active" checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                                <label htmlFor="active" className="text-sm font-medium">
                                    {t('common.active')}
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('customers.section.billing')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('customers.payment_term')} htmlFor="payment_term" error={errors.payment_term}>
                                <NativeSelect id="payment_term" value={data.payment_term} onChange={(e) => setData('payment_term', e.target.value)}>
                                    <option value="cash">{t('customers.term.cash')}</option>
                                    <option value="credit">{t('customers.term.credit')}</option>
                                </NativeSelect>
                            </Field>
                            {!isEdit && canManagePrices && (
                                <Field label={t('customers.default_price')} htmlFor="default_price" error={errors.default_price} optional>
                                    <Input
                                        id="default_price"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        inputMode="decimal"
                                        value={data.default_price}
                                        onChange={(e) => setData('default_price', e.target.value)}
                                        placeholder="0.30"
                                        dir="ltr"
                                    />
                                </Field>
                            )}
                            {data.payment_term === 'credit' && (
                                <>
                                    <Field label={t('customers.credit_limit')} htmlFor="credit_limit" error={errors.credit_limit} optional>
                                        <Input
                                            id="credit_limit"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            inputMode="decimal"
                                            value={data.credit_limit ?? ''}
                                            onChange={(e) => setData('credit_limit', e.target.value)}
                                            dir="ltr"
                                        />
                                    </Field>
                                    <Field label={t('customers.credit_days')} htmlFor="credit_days" error={errors.credit_days} optional>
                                        <Input
                                            id="credit_days"
                                            type="number"
                                            min="0"
                                            max="365"
                                            inputMode="numeric"
                                            value={data.credit_days === '' ? '' : String(data.credit_days)}
                                            onChange={(e) => setData('credit_days', e.target.value === '' ? '' : Number(e.target.value))}
                                            placeholder="30"
                                            dir="ltr"
                                        />
                                    </Field>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('customers.section.delivery')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('customers.route')} htmlFor="route" error={errors.delivery_route_id}>
                                <NativeSelect
                                    id="route"
                                    value={data.delivery_route_id === '' ? '' : String(data.delivery_route_id)}
                                    onChange={(e) => setData('delivery_route_id', e.target.value === '' ? '' : Number(e.target.value))}
                                >
                                    <option value="">{t('customers.no_route')}</option>
                                    {routes.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label={t('customers.stop_sequence')} htmlFor="stop_sequence" error={errors.stop_sequence}>
                                <Input
                                    id="stop_sequence"
                                    type="number"
                                    min="0"
                                    inputMode="numeric"
                                    value={String(data.stop_sequence)}
                                    onChange={(e) => setData('stop_sequence', Number(e.target.value || 0))}
                                    dir="ltr"
                                />
                            </Field>
                            <Field label={t('customers.city')} htmlFor="city" error={errors.city} optional>
                                <Input id="city" value={data.city ?? ''} onChange={(e) => setData('city', e.target.value)} />
                            </Field>
                            <Field label={t('customers.phone')} htmlFor="phone" error={errors.phone} optional>
                                <Input
                                    id="phone"
                                    value={data.phone ?? ''}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    dir="ltr"
                                    inputMode="tel"
                                />
                            </Field>
                            <Field label={t('customers.whatsapp')} htmlFor="whatsapp" error={errors.whatsapp} optional>
                                <Input
                                    id="whatsapp"
                                    value={data.whatsapp ?? ''}
                                    onChange={(e) => setData('whatsapp', e.target.value)}
                                    dir="ltr"
                                    inputMode="tel"
                                />
                            </Field>
                            <Field label={t('customers.map_url')} htmlFor="map_url" error={errors.map_url} optional>
                                <Input id="map_url" value={data.map_url ?? ''} onChange={(e) => setData('map_url', e.target.value)} dir="ltr" />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('customers.section.tax')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('customers.vat_number')} htmlFor="vat_number" error={errors.vat_number} optional>
                                <Input
                                    id="vat_number"
                                    value={data.vat_number ?? ''}
                                    onChange={(e) => setData('vat_number', e.target.value)}
                                    dir="ltr"
                                />
                            </Field>
                            <Field label={t('customers.cr_number')} htmlFor="cr_number" error={errors.cr_number} optional>
                                <Input id="cr_number" value={data.cr_number ?? ''} onChange={(e) => setData('cr_number', e.target.value)} dir="ltr" />
                            </Field>
                            <Field
                                label={t('customers.national_address')}
                                htmlFor="national_address"
                                error={errors.national_address}
                                optional
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="national_address"
                                    value={data.national_address ?? ''}
                                    onChange={(e) => setData('national_address', e.target.value)}
                                />
                            </Field>
                            <Field label={t('common.notes')} htmlFor="notes" error={errors.notes} optional className="sm:col-span-2">
                                <Input id="notes" value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                            </Field>
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-2 lg:col-span-2">
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={route('customers.index')}>{t('common.cancel')}</Link>
                        </Button>
                    </div>
                </form>

                {isEdit && canViewPrices && <PricesPanel customerId={customer.id!} prices={prices} products={products} canManage={canManagePrices} />}
            </div>
        </AppLayout>
    );
}

function PricesPanel({
    customerId,
    prices,
    products,
    canManage,
}: {
    customerId: number;
    prices: PriceRow[];
    products: ProductOption[];
    canManage: boolean;
}) {
    const { t } = useTrans();
    const [productId, setProductId] = useState('');
    const [price, setPrice] = useState('');
    const [effectiveFrom, setEffectiveFrom] = useState(() => new Date().toISOString().slice(0, 10));
    const [saving, setSaving] = useState(false);

    const addPrice = () => {
        if (!price) return;
        setSaving(true);
        router.post(
            route('prices.store'),
            {
                customer_id: customerId,
                product_id: productId === '' ? null : Number(productId),
                price,
                effective_from: effectiveFrom,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPrice('');
                    setProductId('');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{t('customers.section.prices')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {canManage && (
                    <div className="flex flex-wrap items-end gap-2">
                        <Field label={t('products.title')} htmlFor="price_product" className="w-48">
                            <NativeSelect id="price_product" value={productId} onChange={(e) => setProductId(e.target.value)}>
                                <option value="">{t('customers.all_products')}</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name_ar}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <Field label={t('products.default_price')} htmlFor="price_value" className="w-32">
                            <Input
                                id="price_value"
                                type="number"
                                step="0.0001"
                                min="0"
                                inputMode="decimal"
                                value={price}
                                onChange={(e) => setPrice(e.target.value)}
                                dir="ltr"
                            />
                        </Field>
                        <Field label={t('customers.effective_from')} htmlFor="price_date" className="w-40">
                            <Input id="price_date" type="date" value={effectiveFrom} onChange={(e) => setEffectiveFrom(e.target.value)} dir="ltr" />
                        </Field>
                        <Button type="button" onClick={addPrice} disabled={saving || !price}>
                            <Plus className="size-4" />
                            {t('customers.add_price')}
                        </Button>
                    </div>
                )}

                {prices.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{t('common.no_results')}</p>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('products.title')}</TableHead>
                                    <TableHead className="text-end">{t('products.default_price')}</TableHead>
                                    <TableHead>{t('customers.effective_from')}</TableHead>
                                    {canManage && <TableHead className="w-14 text-end">{t('common.actions')}</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {prices.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>{row.product?.name_ar ?? t('customers.all_products')}</TableCell>
                                        <TableCell className="text-end tabular-nums">{fmtPrice(row.price)}</TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {row.effective_from}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-end">
                                                <ConfirmDelete url={route('prices.destroy', row.id)} />
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
