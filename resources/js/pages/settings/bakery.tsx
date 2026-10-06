import { Field } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { useTrans } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type BakerySettings = {
    bakery_name_ar: string;
    bakery_name_en: string;
    vat_number: string;
    cr_number: string;
    national_address: string;
    phone: string;
    vat_enabled: boolean;
    prices_include_vat: boolean;
    vat_rate: string;
    default_credit_days: number;
};

export default function BakerySettingsPage({ settings }: { settings: BakerySettings }) {
    const { t } = useTrans();

    const { data, setData, patch, processing, errors } = useForm<BakerySettings>({ ...settings });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('settings.bakery.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('settings.bakery'), href: '/settings/bakery' }]}>
            <Head title={t('settings.bakery')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={t('settings.bakery')} />

                <form onSubmit={submit} className="grid max-w-3xl gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('settings.section.identity')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('settings.bakery_name_ar')} htmlFor="s_name_ar" error={errors.bakery_name_ar}>
                                <Input
                                    id="s_name_ar"
                                    value={String(data.bakery_name_ar ?? '')}
                                    onChange={(e) => setData('bakery_name_ar', e.target.value)}
                                    required
                                />
                            </Field>
                            <Field label={t('settings.bakery_name_en')} htmlFor="s_name_en" error={errors.bakery_name_en} optional>
                                <Input
                                    id="s_name_en"
                                    value={String(data.bakery_name_en ?? '')}
                                    onChange={(e) => setData('bakery_name_en', e.target.value)}
                                    dir="ltr"
                                />
                            </Field>
                            <Field label={t('settings.vat_number')} htmlFor="s_vat_no" error={errors.vat_number} optional>
                                <Input
                                    id="s_vat_no"
                                    value={String(data.vat_number ?? '')}
                                    onChange={(e) => setData('vat_number', e.target.value)}
                                    dir="ltr"
                                />
                            </Field>
                            <Field label={t('settings.cr_number')} htmlFor="s_cr" error={errors.cr_number} optional>
                                <Input
                                    id="s_cr"
                                    value={String(data.cr_number ?? '')}
                                    onChange={(e) => setData('cr_number', e.target.value)}
                                    dir="ltr"
                                />
                            </Field>
                            <Field label={t('settings.national_address')} htmlFor="s_addr" error={errors.national_address} optional>
                                <Input
                                    id="s_addr"
                                    value={String(data.national_address ?? '')}
                                    onChange={(e) => setData('national_address', e.target.value)}
                                />
                            </Field>
                            <Field label={t('settings.phone')} htmlFor="s_phone" error={errors.phone} optional>
                                <Input
                                    id="s_phone"
                                    value={String(data.phone ?? '')}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    dir="ltr"
                                    inputMode="tel"
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('settings.section.vat')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="s_vat_enabled"
                                    checked={Boolean(data.vat_enabled)}
                                    onCheckedChange={(v) => setData('vat_enabled', v === true)}
                                />
                                <label htmlFor="s_vat_enabled" className="text-sm font-medium">
                                    {t('settings.vat_enabled')}
                                </label>
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="s_vat_incl"
                                    checked={Boolean(data.prices_include_vat)}
                                    onCheckedChange={(v) => setData('prices_include_vat', v === true)}
                                />
                                <label htmlFor="s_vat_incl" className="text-sm font-medium">
                                    {t('settings.prices_include_vat')}
                                </label>
                            </div>
                            <Field label={t('settings.vat_rate')} htmlFor="s_vat_rate" error={errors.vat_rate}>
                                <Input
                                    id="s_vat_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    inputMode="decimal"
                                    value={String(data.vat_rate ?? '')}
                                    onChange={(e) => setData('vat_rate', e.target.value)}
                                    dir="ltr"
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('settings.section.defaults')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('settings.default_credit_days')} htmlFor="s_credit_days" error={errors.default_credit_days}>
                                <Input
                                    id="s_credit_days"
                                    type="number"
                                    min="0"
                                    max="365"
                                    inputMode="numeric"
                                    value={String(data.default_credit_days ?? '')}
                                    onChange={(e) => setData('default_credit_days', Number(e.target.value || 0))}
                                    dir="ltr"
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
