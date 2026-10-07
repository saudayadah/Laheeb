import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { Copy, FileDown, MessageCircle, Printer } from 'lucide-react';
import { useState } from 'react';

interface StatementRow {
    id: number;
    date: string;
    description: string | null;
    debit: string;
    credit: string;
    balance: string;
}

interface StatementProps {
    customer: { id: number; code: string; name: string; phone?: string | null; whatsapp?: string | null; vat_number: string | null };
    from: string;
    to: string;
    statement: { opening: string; rows: StatementRow[]; closing: string };
    bakeryName: string;
    publicUrl: string | null;
    waUrl: string | null;
    isPublic: boolean;
}

export default function StatementShow({ customer, from, to, statement, bakeryName, publicUrl, waUrl, isPublic }: StatementProps) {
    const { t } = useTrans();
    const [copied, setCopied] = useState(false);

    const changeRange = (updates: { from?: string; to?: string }) =>
        router.get(route('statements.show', customer.id), { from, to, ...updates }, { preserveState: true, replace: true });

    const copyLink = async () => {
        if (!publicUrl) return;
        try {
            await navigator.clipboard.writeText(publicUrl);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard may be unavailable; the link is still visible in WhatsApp.
        }
    };

    const content = (
        <div className="mx-auto max-w-3xl p-4 print:max-w-none print:p-0">
            <Head title={`${t('statements.title')} — ${customer.name}`} />
            <style>{`@media print { @page { size: A4; margin: 12mm; } body { background: white; } }`}</style>

            {!isPublic && (
                <div className="mb-4 flex flex-wrap items-center gap-2 print:hidden">
                    <Input
                        type="date"
                        dir="ltr"
                        className="w-38"
                        value={from}
                        aria-label={t('common.from')}
                        onChange={(e) => changeRange({ from: e.target.value })}
                    />
                    <span className="text-muted-foreground">—</span>
                    <Input
                        type="date"
                        dir="ltr"
                        className="w-38"
                        value={to}
                        aria-label={t('common.to')}
                        onChange={(e) => changeRange({ to: e.target.value })}
                    />
                    <span className="flex-1" />
                    <Button size="sm" onClick={() => window.print()}>
                        <Printer className="size-4" /> {t('orders.print')}
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <a href={route('statements.pdf', { customer: customer.id, from, to })}>
                            <FileDown className="size-4" /> PDF
                        </a>
                    </Button>
                    {waUrl && (
                        <Button size="sm" variant="outline" asChild>
                            <a href={waUrl} target="_blank" rel="noreferrer">
                                <MessageCircle className="size-4" /> {t('statements.share_wa')}
                            </a>
                        </Button>
                    )}
                    {publicUrl && (
                        <Button size="sm" variant="outline" onClick={copyLink} title={t('statements.link_hint')}>
                            <Copy className="size-4" /> {copied ? t('statements.link_copied') : t('statements.copy_link')}
                        </Button>
                    )}
                </div>
            )}

            <div className="rounded-lg border border-neutral-200 bg-white p-6 text-black print:border-0 print:p-0">
                <div className="mb-4 text-center">
                    <h1 className="text-lg font-bold">{bakeryName}</h1>
                    <h2 className="font-semibold">{t('statements.title')} / Account Statement</h2>
                    <div className="text-sm">
                        {customer.name} ({customer.code})
                        {customer.vat_number && (
                            <span className="ms-3">
                                {t('customers.vat_number')}: <span dir="ltr">{customer.vat_number}</span>
                            </span>
                        )}
                    </div>
                    <div className="text-xs text-neutral-500" dir="ltr">
                        {from} — {to}
                    </div>
                </div>

                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="bg-neutral-100">
                            <th className="border border-neutral-300 px-2 py-1.5 text-start">{t('common.date')}</th>
                            <th className="border border-neutral-300 px-2 py-1.5 text-start">{t('common.notes')}</th>
                            <th className="border border-neutral-300 px-2 py-1.5 text-end">{t('statements.debit')}</th>
                            <th className="border border-neutral-300 px-2 py-1.5 text-end">{t('statements.credit')}</th>
                            <th className="border border-neutral-300 px-2 py-1.5 text-end">{t('suppliers.balance')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="bg-neutral-50 font-medium">
                            <td className="border border-neutral-300 px-2 py-1.5" colSpan={4}>
                                {t('statements.opening')}
                            </td>
                            <td className="border border-neutral-300 px-2 py-1.5 text-end tabular-nums">{fmtAmount(statement.opening)}</td>
                        </tr>
                        {statement.rows.map((row) => (
                            <tr key={row.id}>
                                <td className="border border-neutral-300 px-2 py-1 whitespace-nowrap tabular-nums" dir="ltr">
                                    {row.date}
                                </td>
                                <td className="border border-neutral-300 px-2 py-1">{row.description}</td>
                                <td className="border border-neutral-300 px-2 py-1 text-end tabular-nums">
                                    {parseFloat(row.debit) > 0 ? fmtAmount(row.debit) : ''}
                                </td>
                                <td className="border border-neutral-300 px-2 py-1 text-end text-emerald-700 tabular-nums">
                                    {parseFloat(row.credit) > 0 ? fmtAmount(row.credit) : ''}
                                </td>
                                <td className="border border-neutral-300 px-2 py-1 text-end font-medium tabular-nums">{fmtAmount(row.balance)}</td>
                            </tr>
                        ))}
                        <tr className="bg-neutral-100 text-base font-bold">
                            <td className="border border-neutral-300 px-2 py-2" colSpan={4}>
                                {t('statements.closing')}
                            </td>
                            <td
                                className={`border border-neutral-300 px-2 py-2 text-end tabular-nums ${parseFloat(statement.closing) > 0 ? 'text-red-700' : ''}`}
                            >
                                {fmtAmount(statement.closing)} {t('common.currency')}
                            </td>
                        </tr>
                    </tbody>
                </table>

                {isPublic && (
                    <div className="mt-6 flex justify-center print:hidden">
                        <Button size="sm" variant="outline" onClick={() => window.print()}>
                            <Printer className="size-4" /> {t('orders.print')}
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );

    return isPublic ? (
        content
    ) : (
        <AppLayout
            breadcrumbs={[
                { title: t('customers.title'), href: '/customers' },
                { title: `${t('statements.title')} — ${customer.name}`, href: '#' },
            ]}
        >
            {content}
        </AppLayout>
    );
}
