import { Button } from '@/components/ui/button';
import { fmtAmount, fmtPrice } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { bluetoothAvailable, printElementViaBluetooth } from '@/lib/thermal-print';
import { type InvoiceData } from '@/pages/invoices/show';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Bluetooth, LoaderCircle, Printer } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useRef, useState } from 'react';

interface BakeryInfo {
    name_ar: string;
    name_en: string;
    vat_number: string;
    cr_number: string;
    national_address: string;
    phone: string;
    vat_enabled: boolean;
}

export default function ThermalReceipt({ invoice, bakery, backTo }: { invoice: InvoiceData; bakery: BakeryInfo; backTo: string }) {
    const { t } = useTrans();
    const receiptRef = useRef<HTMLDivElement>(null);
    const [btState, setBtState] = useState<'idle' | 'working' | 'done' | 'failed'>('idle');
    const [btStep, setBtStep] = useState('');

    const printBluetooth = async () => {
        if (!receiptRef.current) return;
        setBtState('working');
        try {
            await printElementViaBluetooth(receiptRef.current, (step) => setBtStep(t(`thermal.bt_${step}`)));
            setBtState('done');
        } catch {
            setBtState('failed');
        }
    };

    return (
        <div className="mx-auto flex max-w-sm flex-col items-center gap-4 p-4 print:max-w-none print:p-0">
            <Head title={invoice.number} />
            <style>{`@media print { @page { size: 80mm auto; margin: 2mm; } body { background: white; } }`}</style>

            <div className="flex flex-wrap justify-center gap-2 print:hidden">
                {bluetoothAvailable() && (
                    <Button size="sm" onClick={printBluetooth} disabled={btState === 'working'}>
                        {btState === 'working' ? <LoaderCircle className="size-4 animate-spin" /> : <Bluetooth className="size-4" />}
                        {t('thermal.bt')}
                    </Button>
                )}
                <Button size="sm" variant={bluetoothAvailable() ? 'outline' : 'default'} onClick={() => window.print()}>
                    <Printer className="size-4" /> {t('orders.print')}
                </Button>
                <Button variant="outline" size="sm" asChild>
                    <Link href={backTo}>
                        <ArrowRight className="size-4" /> {t('common.back')}
                    </Link>
                </Button>
            </div>

            {btState === 'working' && <p className="text-muted-foreground text-xs print:hidden">{btStep}</p>}
            {btState === 'done' && <p className="text-xs text-emerald-600 print:hidden">{t('thermal.bt_done')}</p>}
            {btState === 'failed' && <p className="text-xs text-red-600 print:hidden">{t('thermal.bt_failed')}</p>}

            <div ref={receiptRef} className="w-[72mm] border bg-white p-2 text-center text-[11px] leading-snug text-black print:border-0">
                <div className="text-sm font-bold">{bakery.name_ar}</div>
                {bakery.vat_enabled && bakery.vat_number && (
                    <div>
                        {t('settings.vat_number')}: <span dir="ltr">{bakery.vat_number}</span>
                    </div>
                )}
                {bakery.phone && <div dir="ltr">{bakery.phone}</div>}

                <div className="my-1 border-t border-dashed border-black" />
                <div className="font-bold">{invoice.type === 'tax' ? t('invoices.tax_invoice') : t('invoices.simplified_invoice')}</div>
                <div dir="ltr">{invoice.number}</div>
                <div dir="ltr">{invoice.posted_at ?? invoice.date}</div>
                {invoice.customer && (
                    <div>
                        {invoice.customer.name} ({invoice.customer.code})
                    </div>
                )}

                <div className="my-1 border-t border-dashed border-black" />

                <table className="w-full text-[11px]">
                    <thead>
                        <tr className="border-b border-black">
                            <th className="py-0.5 text-start">{t('invoices.item')}</th>
                            <th className="py-0.5 text-center">{t('invoices.qty')}</th>
                            <th className="py-0.5 text-center">{t('invoices.price')}</th>
                            <th className="py-0.5 text-end">{t('invoices.line_total')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoice.lines.map((line) => (
                            <tr key={line.id}>
                                <td className="py-0.5 text-start">{line.name}</td>
                                <td className="py-0.5 text-center tabular-nums">{line.qty}</td>
                                <td className="py-0.5 text-center tabular-nums">{fmtPrice(line.unit_price)}</td>
                                <td className="py-0.5 text-end tabular-nums">{fmtAmount(line.line_total)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="my-1 border-t border-dashed border-black" />

                <div className="flex justify-between">
                    <span>{t('invoices.subtotal')}</span>
                    <span className="tabular-nums">{fmtAmount(invoice.subtotal)}</span>
                </div>
                <div className="flex justify-between">
                    <span>{t('invoices.vat')}</span>
                    <span className="tabular-nums">{fmtAmount(invoice.vat_amount)}</span>
                </div>
                <div className="flex justify-between text-sm font-bold">
                    <span>{t('invoices.total')}</span>
                    <span className="tabular-nums">{fmtAmount(invoice.total)}</span>
                </div>
                <div className="mt-0.5">{t(`invoices.method.${invoice.payment_method}`)}</div>

                {invoice.qr_payload && (
                    <div className="mt-2 flex justify-center">
                        <QRCodeSVG value={invoice.qr_payload} size={100} />
                    </div>
                )}

                {invoice.status === 'void' && <div className="mt-1 text-sm font-bold">*** {t('invoices.status.void')} ***</div>}
            </div>
        </div>
    );
}
