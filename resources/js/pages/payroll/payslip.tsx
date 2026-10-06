import { Button } from '@/components/ui/button';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type PayrollLineData } from '@/pages/payroll/show';
import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

export default function Payslip({ period, line, bakeryName }: { period: string; line: PayrollLineData; bakeryName: string }) {
    const { t } = useTrans();

    const rows: [string, string, boolean][] = [
        [`${t('payroll.basic')} / Basic`, line.basic, true],
        [`${t('payroll.overtime')} / Overtime`, line.overtime, true],
        [`${t('payroll.leave_allowance')} / Leave allowance`, line.leave_allowance, true],
        [`${t('payroll.additions')} / Additions`, line.additions, true],
        [`${t('payroll.absence')} / Absence`, line.absence_amount, false],
        [`${t('payroll.deductions')} / Deductions`, line.deductions, false],
        [`${t('payroll.advance_recovery')} / Advance recovery`, line.advance_recovery, false],
        [`${t('payroll.charges_recovery')} / Charges`, line.charges_recovery, false],
    ];

    return (
        <div className="mx-auto max-w-md p-4 print:max-w-none print:p-0">
            <Head title={`${t('payroll.payslip')} ${period}`} />
            <style>{`@media print { @page { size: A5; margin: 10mm; } body { background: white; } }`}</style>

            <div className="mb-4 flex gap-2 print:hidden">
                <Button size="sm" onClick={() => window.print()}>
                    <Printer className="size-4" /> {t('orders.print')}
                </Button>
                <Button variant="outline" size="sm" onClick={() => window.history.back()}>
                    {t('common.back')}
                </Button>
            </div>

            <div className="rounded-lg border bg-white p-6 text-black print:border-2 print:border-black">
                <div className="mb-4 text-center">
                    <div className="text-lg font-bold">{bakeryName}</div>
                    <div className="font-semibold">
                        {t('payroll.payslip')} / Payslip — <span dir="ltr">{period}</span>
                    </div>
                </div>

                <div className="mb-3 flex justify-between border-b pb-2 text-sm">
                    <span className="font-semibold">{line.employee.name_ar}</span>
                    <span>{line.employee.job ? t(`employees.job.${line.employee.job}`) : ''}</span>
                </div>

                <table className="w-full text-sm">
                    <tbody>
                        {rows.map(([label, value, positive], i) =>
                            parseFloat(value) > 0 || i === 0 ? (
                                <tr key={label}>
                                    <td className="border-b border-dotted py-1.5">{label}</td>
                                    <td className={`border-b border-dotted py-1.5 text-end tabular-nums ${positive ? '' : 'text-red-600'}`}>
                                        {positive ? '' : '-'}
                                        {fmtAmount(value)}
                                    </td>
                                </tr>
                            ) : null,
                        )}
                        <tr className="text-base font-bold">
                            <td className="py-2">{t('payroll.net')} / Net Salary</td>
                            <td className="py-2 text-end tabular-nums">
                                {fmtAmount(line.net)} {t('common.currency')}
                            </td>
                        </tr>
                        {line.payment_method && (
                            <tr className="text-xs text-neutral-500">
                                <td>{t('payroll.method')}</td>
                                <td className="text-end">
                                    {t(`payroll.method.${line.payment_method}`)}
                                    {line.paid_at && (
                                        <span className="ms-1 tabular-nums" dir="ltr">
                                            ({line.paid_at})
                                        </span>
                                    )}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>

                <div className="mt-10 grid grid-cols-2 gap-8 text-center text-xs text-neutral-500">
                    <div className="border-t border-black pt-1">{t('payroll.sig_accountant')}</div>
                    <div className="border-t border-black pt-1">{t('payroll.sig_employee')}</div>
                </div>
            </div>
        </div>
    );
}
