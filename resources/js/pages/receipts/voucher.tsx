import { Button } from '@/components/ui/button';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Printer } from 'lucide-react';

interface VoucherProps {
    receipt: {
        id: number;
        number: string;
        date: string;
        amount: string;
        method: string;
        reference: string | null;
        status: string;
        customer: { id: number; name: string; code: string } | null;
        group: { id: number; name: string } | null;
        receiver: string | null;
        notes: string | null;
        allocations: { invoice: string; amount: string }[];
    };
    bakeryName: string;
    backTo: string;
}

export default function ReceiptVoucher({ receipt, bakeryName, backTo }: VoucherProps) {
    const { t } = useTrans();

    return (
        <div className="mx-auto max-w-md p-4 print:max-w-none print:p-0">
            <Head title={receipt.number} />
            <style>{`@media print { @page { size: A5 landscape; margin: 8mm; } body { background: white; } }`}</style>

            <div className="mb-4 flex gap-2 print:hidden">
                <Button size="sm" onClick={() => window.print()}>
                    <Printer className="size-4" /> {t('orders.print')}
                </Button>
                <Button variant="outline" size="sm" asChild>
                    <Link href={backTo}>
                        <ArrowRight className="size-4 ltr:rotate-180" /> {t('common.back')}
                    </Link>
                </Button>
            </div>

            <div className="rounded-lg border bg-white p-6 text-black print:border-2 print:border-black">
                <div className="mb-4 text-center">
                    <div className="text-lg font-bold">{bakeryName}</div>
                    <div className="mt-1 text-base font-semibold">{t('receipts.voucher')}</div>
                    <div className="text-sm tabular-nums" dir="ltr">
                        {receipt.number} · {receipt.date}
                    </div>
                </div>

                <div className="flex flex-col gap-2 text-sm">
                    <div className="flex justify-between border-b border-dotted pb-1">
                        <span className="text-neutral-500">{t('receipts.received_from')}</span>
                        <span className="font-semibold">
                            {receipt.customer
                                ? `${receipt.customer.name} (${receipt.customer.code})`
                                : `${t('receipts.for_group')} ${receipt.group?.name}`}
                        </span>
                    </div>
                    <div className="flex justify-between border-b border-dotted pb-1">
                        <span className="text-neutral-500">{t('receipts.the_sum')}</span>
                        <span className="text-xl font-bold tabular-nums">
                            {fmtAmount(receipt.amount)} {t('common.currency')}
                        </span>
                    </div>
                    <div className="flex justify-between border-b border-dotted pb-1">
                        <span className="text-neutral-500">{t('invoices.method')}</span>
                        <span>
                            {t(`receipts.method.${receipt.method}`)}
                            {receipt.reference && (
                                <span className="ms-2 tabular-nums" dir="ltr">
                                    ({receipt.reference})
                                </span>
                            )}
                        </span>
                    </div>
                    {receipt.receiver && (
                        <div className="flex justify-between border-b border-dotted pb-1">
                            <span className="text-neutral-500">{t('receipts.received_by')}</span>
                            <span>{receipt.receiver}</span>
                        </div>
                    )}
                </div>

                {receipt.allocations.length > 0 && (
                    <div className="mt-3 text-xs">
                        <div className="mb-1 font-medium">{t('receipts.allocations')}:</div>
                        {receipt.allocations.map((allocation, i) => (
                            <div key={i} className="flex justify-between">
                                <span dir="ltr" className="tabular-nums">
                                    {allocation.invoice}
                                </span>
                                <span className="tabular-nums">{fmtAmount(allocation.amount)}</span>
                            </div>
                        ))}
                    </div>
                )}

                {receipt.status === 'void' && (
                    <div className="mt-3 text-center text-lg font-bold text-red-600">*** {t('invoices.status.void')} ***</div>
                )}

                <div className="mt-8 grid grid-cols-2 gap-8 text-center text-xs text-neutral-500">
                    <div className="border-t border-black pt-1">{t('receipts.received_by')}</div>
                    <div className="border-t border-black pt-1">{t('customers.name')}</div>
                </div>
            </div>
        </div>
    );
}
