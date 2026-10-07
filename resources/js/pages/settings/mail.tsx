import { Field } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { useTrans } from '@/lib/i18n';
import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Send } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type MailSettings = {
    mail_host: string;
    mail_port: string;
    mail_username: string;
    mail_from_name: string;
    mail_password: string;
};

export default function MailSettingsPage({
    settings,
    testRecipient,
}: {
    settings: Omit<MailSettings, 'mail_password'> & { has_password: boolean };
    testRecipient: string;
}) {
    const { t } = useTrans();
    const [testing, setTesting] = useState(false);

    const { data, setData, patch, processing, errors } = useForm<MailSettings>({
        mail_host: settings.mail_host,
        mail_port: settings.mail_port || '465',
        mail_username: settings.mail_username,
        mail_from_name: settings.mail_from_name,
        mail_password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('settings.mail.update'), { preserveScroll: true });
    };

    const sendTest = () => {
        setTesting(true);
        router.post(route('settings.mail.test'), {}, { preserveScroll: true, onFinish: () => setTesting(false) });
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('settings.mail'), href: '/settings/mail' }]}>
            <Head title={t('settings.mail')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={t('settings.mail')} />

                <p className="text-muted-foreground max-w-3xl text-sm">{t('settings.mail_hint')}</p>

                <form onSubmit={submit} className="grid max-w-3xl gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('settings.mail_section')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('settings.mail_username')} htmlFor="m_user" error={errors.mail_username}>
                                <Input
                                    id="m_user"
                                    type="email"
                                    value={data.mail_username}
                                    onChange={(e) => setData('mail_username', e.target.value)}
                                    placeholder="no-reply@laheeb.sa"
                                    dir="ltr"
                                    required
                                />
                            </Field>
                            <Field label={t('settings.mail_password')} htmlFor="m_pass" error={errors.mail_password} optional={settings.has_password}>
                                <Input
                                    id="m_pass"
                                    type="password"
                                    value={data.mail_password}
                                    onChange={(e) => setData('mail_password', e.target.value)}
                                    placeholder={settings.has_password ? t('settings.mail_password_keep') : ''}
                                    dir="ltr"
                                    autoComplete="new-password"
                                />
                            </Field>
                            <Field label={t('settings.mail_host')} htmlFor="m_host" error={errors.mail_host}>
                                <Input
                                    id="m_host"
                                    value={data.mail_host}
                                    onChange={(e) => setData('mail_host', e.target.value)}
                                    placeholder="mail.laheeb.sa"
                                    dir="ltr"
                                    required
                                />
                            </Field>
                            <Field label={t('settings.mail_port')} htmlFor="m_port" error={errors.mail_port}>
                                <Input
                                    id="m_port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    inputMode="numeric"
                                    value={data.mail_port}
                                    onChange={(e) => setData('mail_port', e.target.value)}
                                    dir="ltr"
                                    required
                                />
                            </Field>
                            <Field label={t('settings.mail_from_name')} htmlFor="m_from" error={errors.mail_from_name} optional>
                                <Input
                                    id="m_from"
                                    value={data.mail_from_name}
                                    onChange={(e) => setData('mail_from_name', e.target.value)}
                                    placeholder={t('settings.mail_from_placeholder')}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                        <Button type="button" variant="outline" disabled={testing || processing} onClick={sendTest}>
                            {testing ? <LoaderCircle className="size-4 animate-spin" /> : <Send className="size-4" />}
                            {t('settings.mail_send_test')}
                        </Button>
                        <span className="text-muted-foreground text-xs" dir="ltr">
                            → {testRecipient}
                        </span>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
