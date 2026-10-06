import { Button } from '@/components/ui/button';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type PayrollRunData } from '@/pages/payroll/show';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Printer } from 'lucide-react';

export default function PayrollSheet({ run, bakeryName }: { run: PayrollRunData; bakeryName: string }) {
    const { t } = useTrans();
    const totalNet = run.lines.reduce((sum, line) => sum + parseFloat(line.net), 0);

    return (
        <div className="mx-auto max-w-5xl p-6 text-black print:p-0">
            <Head title={`${t('payroll.sheet')} ${run.period}`} />
            <style>{`@media print { @page { size: A4 landscape; margin: 10mm; } body { background: white; } }`}</style>

            <div className="mb-4 flex gap-2 print:hidden">
                <Button size="sm" onClick={() => window.print()}>
                    <Printer className="size-4" /> {t('orders.print')}
                </Button>
                <Button variant="outline" size="sm" asChild>
                    <Link href={route('payroll.show', run.id)}>
                        <ArrowRight className="size-4" /> {t('common.back')}
                    </Link>
                </Button>
            </div>

            <div className="mb-4 text-center">
                <h1 className="text-lg font-bold">{bakeryName}</h1>
                <h2 className="text-base font-semibold">
                    {t('payroll.sheet')} — <span dir="ltr">{run.period}</span>
                </h2>
            </div>

            <table className="w-full border-collapse text-xs">
                <thead>
                    <tr className="bg-neutral-100">
                        {[
                            '#',
                            t('payroll.employee'),
                            t('payroll.basic'),
                            t('payroll.overtime'),
                            t('payroll.leave_allowance'),
                            t('payroll.additions'),
                            t('payroll.absence'),
                            t('payroll.deductions'),
                            t('payroll.advance_recovery'),
                            t('payroll.charges_recovery'),
                            t('payroll.net'),
                            t('payroll.sig_employee'),
                        ].map((heading, i) => (
                            <th key={i} className="border border-black px-1.5 py-1.5 whitespace-nowrap">
                                {heading}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {run.lines.map((line, i) => (
                        <tr key={line.id}>
                            <td className="border border-black px-1.5 py-1 text-center tabular-nums">{i + 1}</td>
                            <td className="border border-black px-1.5 py-1 whitespace-nowrap">{line.employee.name_ar}</td>
                            <td className="border border-black px-1.5 py-1 text-end tabular-nums">{fmtAmount(line.basic)}</td>
                            <td className="border border-black px-1.5 py-1 text-end tabular-nums">
                                {parseFloat(line.overtime) > 0 ? fmtAmount(line.overtime) : ''}
                            </td>
                            <td className="border border-black px-1.5 py-1 text-end tabular-nums">
                                {parseFloat(line.leave_allowance) > 0 ? fmtAmount(line.leave_allowance) : ''}
                            </td>
                            <td className="border border-black px-1.5 py-1 text-end tabular-nums">
                                {parseFloat(line.additions) > 0 ? fmtAmount(line.additions) : ''}
                            </td>
                            <td className="border border-black px-1.5 py-1 text-end tabular-nums">
                                {parseFloat(line.absence_amount) > 0 ? fmtAmount(line.absence_amount) : ''}
                            </td>
                            <td className="border border-black px-1.5 py-1 text-end tabular-nums">
                                {parseFloat(line.deductions) > 0 ? fmtAmount(line.deductions) : ''}
                            </td>
                            <td className="border border-black px-1.5 py-1 text-end tabular-nums">
                                {parseFloat(line.advance_recovery) > 0 ? fmtAmount(line.advance_recovery) : ''}
                            </td>
                            <td className="border border-black px-1.5 py-1 text-end tabular-nums">
                                {parseFloat(line.charges_recovery) > 0 ? fmtAmount(line.charges_recovery) : ''}
                            </td>
                            <td className="border border-black px-1.5 py-1 text-end font-bold tabular-nums">{fmtAmount(line.net)}</td>
                            <td className="w-24 border border-black" />
                        </tr>
                    ))}
                    <tr className="bg-neutral-100 font-bold">
                        <td colSpan={10} className="border border-black px-1.5 py-1.5">
                            {t('payroll.total_net')}
                        </td>
                        <td className="border border-black px-1.5 py-1.5 text-end tabular-nums">{fmtAmount(totalNet)}</td>
                        <td className="border border-black" />
                    </tr>
                </tbody>
            </table>

            <div className="mt-12 grid grid-cols-2 gap-16 text-center text-sm">
                <div className="border-t border-black pt-2 font-medium">{t('payroll.sig_accountant')}</div>
                <div className="border-t border-black pt-2 font-medium">{t('payroll.sig_manager')}</div>
            </div>
        </div>
    );
}
