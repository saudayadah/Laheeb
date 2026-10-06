import { Button } from '@/components/ui/button';
import { fmtAmount, fmtPrice } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type InvoiceData } from '@/pages/invoices/show';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Printer } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';

interface BakeryInfo {
    name_ar: string;
    name_en: string;
    vat_number: string;
    cr_number: string;
    national_address: string;
    phone: string;
    vat_enabled: boolean;
}

export default function InvoicePrint({ invoice, bakery }: { invoice: InvoiceData; bakery: BakeryInfo }) {
    const { t } = useTrans();

    return (
        <div className="mx-auto max-w-3xl p-6 text-black print:p-0">
            <Head title={invoice.number} />
            <style>{`@media print { @page { size: A4; margin: 12mm; } body { background: white; } }`}</style>

            <div className="mb-4 flex gap-2 print:hidden">
                <Button size="sm" onClick={() => window.print()}>
                    <Printer className="size-4" /> {t('orders.print')}
                </Button>
                <Button variant="outline" size="sm" asChild>
                    <Link href={route('invoices.show', invoice.id)}>
                        <ArrowRight className="size-4" /> {t('common.back')}
                    </Link>
                </Button>
            </div>

            <div className="border bg-white p-6 print:border-0 print:p-0">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold">{bakery.name_ar}</h1>
                        {bakery.name_en && <div className="text-sm">{bakery.name_en}</div>}
                        {bakery.national_address && <div className="mt-1 text-xs">{bakery.national_address}</div>}
                        {bakery.phone && (
                            <div className="text-xs" dir="ltr">
                                {bakery.phone}
                            </div>
                        )}
                        <div className="mt-1 text-xs">
                            {bakery.cr_number && (
                                <span>
                                    {t('settings.cr_number')}: <span dir="ltr">{bakery.cr_number}</span>
                                </span>
                            )}
                            {bakery.vat_number && (
                                <span className="ms-3">
                                    {t('settings.vat_number')}: <span dir="ltr">{bakery.vat_number}</span>
                                </span>
                            )}
                        </div>
                    </div>
                    {invoice.qr_payload && <QRCodeSVG value={invoice.qr_payload} size={110} />}
                </div>

                <div className="my-4 rounded border p-3 text-center">
                    <div className="text-lg font-bold">
                        {invoice.type === 'tax' ? t('invoices.tax_invoice') : t('invoices.simplified_invoice')}
                        <span className="ms-2 text-sm font-normal">Tax Invoice</span>
                    </div>
                    <div className="mt-1 flex flex-wrap justify-center gap-x-6 text-sm">
                        <span>
                            {t('invoices.number')}:{' '}
                            <span dir="ltr" className="font-semibold tabular-nums">
                                {invoice.number}
                            </span>
                        </span>
                        <span>
                            {t('common.date')}:{' '}
                            <span dir="ltr" className="tabular-nums">
                                {invoice.posted_at ?? invoice.date}
                            </span>
                        </span>
                        <span>
                            {t('invoices.method')}: {t(`invoices.method.${invoice.payment_method}`)}
                        </span>
                    </div>
                </div>

                {invoice.customer && (
                    <div className="mb-4 rounded border p-3 text-sm">
                        <div className="font-semibold">
                            {t('customers.name')}: {invoice.customer.name} ({invoice.customer.code})
                        </div>
                        {invoice.customer.vat_number && (
                            <div>
                                {t('customers.vat_number')}: <span dir="ltr">{invoice.customer.vat_number}</span>
                            </div>
                        )}
                        {invoice.customer.national_address && <div>{invoice.customer.national_address}</div>}
                    </div>
                )}

                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="bg-neutral-100">
                            <th className="border px-2 py-1.5 text-start">{t('invoices.item')} / Item</th>
                            <th className="border px-2 py-1.5 text-center">{t('invoices.qty')} / Qty</th>
                            <th className="border px-2 py-1.5 text-center">{t('invoices.price')} / Price</th>
                            <th className="border px-2 py-1.5 text-center">{t('invoices.vat')} / VAT</th>
                            <th className="border px-2 py-1.5 text-end">{t('invoices.line_total')} / Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoice.lines.map((line) => (
                            <tr key={line.id}>
                                <td className="border px-2 py-1.5">{line.name}</td>
                                <td className="border px-2 py-1.5 text-center tabular-nums">{line.qty}</td>
                                <td className="border px-2 py-1.5 text-center tabular-nums">{fmtPrice(line.unit_price)}</td>
                                <td className="border px-2 py-1.5 text-center tabular-nums">{fmtAmount(line.line_vat)}</td>
                                <td className="border px-2 py-1.5 text-end tabular-nums">{fmtAmount(line.line_total)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="mt-4 flex justify-end">
                    <div className="w-72 text-sm">
                        <div className="flex justify-between border-b py-1">
                            <span>{t('invoices.subtotal')} / Subtotal</span>
                            <span className="tabular-nums">{fmtAmount(invoice.subtotal)}</span>
                        </div>
                        <div className="flex justify-between border-b py-1">
                            <span>{t('invoices.vat')} / VAT</span>
                            <span className="tabular-nums">{fmtAmount(invoice.vat_amount)}</span>
                        </div>
                        <div className="flex justify-between py-2 text-base font-bold">
                            <span>{t('invoices.total')} / Total</span>
                            <span className="tabular-nums">
                                {fmtAmount(invoice.total)} {t('common.currency')}
                            </span>
                        </div>
                    </div>
                </div>

                {bakery.vat_enabled && invoice.prices_include_vat && (
                    <p className="mt-2 text-center text-xs text-neutral-500">{t('invoices.vat_included_note')}</p>
                )}

                {invoice.status === 'void' && (
                    <div className="mt-4 text-center text-xl font-bold text-red-600">*** {t('invoices.status.void')} ***</div>
                )}
            </div>
        </div>
    );
}
