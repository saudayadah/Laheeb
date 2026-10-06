import { Button } from '@/components/ui/button';
import { fmtInt } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Printer } from 'lucide-react';

interface ProductionProps {
    date: string;
    weekday: number;
    summary: {
        products: { id: number; name_ar: string; size_cm: number | null }[];
        totalsByProduct: Record<number, number>;
        routes: { name: string | null; products: Record<number, number>; total: number }[];
        grandTotal: number;
    };
}

export default function ProductionSheet({ date, weekday, summary }: ProductionProps) {
    const { t } = useTrans();

    const productsWithQty = summary.products.filter((p) => (summary.totalsByProduct[p.id] ?? 0) > 0);

    return (
        <div className="mx-auto max-w-4xl p-6 print:p-0">
            <Head title={t('orders.production_sheet')} />

            <div className="mb-4 flex items-center gap-2 print:hidden">
                <Button variant="outline" size="sm" asChild>
                    <Link href={route('orders.grid', { date })}>
                        <ArrowRight className="size-4" /> {t('common.back')}
                    </Link>
                </Button>
                <Button size="sm" onClick={() => window.print()}>
                    <Printer className="size-4" /> {t('orders.print')}
                </Button>
            </div>

            <div className="mb-6 text-center">
                <h1 className="text-xl font-bold">{t('orders.production_sheet')}</h1>
                <p className="text-sm">
                    {t(`weekday.${weekday}`)} — <span dir="ltr">{date}</span>
                </p>
            </div>

            {/* Big totals per product for the bakers */}
            <table className="w-full border-collapse text-lg">
                <thead>
                    <tr>
                        <th className="border border-black px-3 py-2 text-start">{t('orders.product')}</th>
                        <th className="border border-black px-3 py-2 text-center">{t('orders.qty')}</th>
                    </tr>
                </thead>
                <tbody>
                    {productsWithQty.map((p) => (
                        <tr key={p.id}>
                            <td className="border border-black px-3 py-2 font-medium">{p.name_ar}</td>
                            <td className="border border-black px-3 py-2 text-center text-xl font-bold tabular-nums">
                                {fmtInt(summary.totalsByProduct[p.id])}
                            </td>
                        </tr>
                    ))}
                    <tr>
                        <td className="border border-black px-3 py-2 font-bold">{t('orders.grand_total')}</td>
                        <td className="border border-black px-3 py-2 text-center text-xl font-bold tabular-nums">{fmtInt(summary.grandTotal)}</td>
                    </tr>
                </tbody>
            </table>

            {/* Per-route breakdown for loading */}
            {summary.routes.length > 0 && (
                <>
                    <h2 className="mt-8 mb-3 text-lg font-bold">
                        {t('orders.loading_sheet')} — {t('nav.routes')}
                    </h2>
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className="border border-black px-2 py-1 text-start">{t('customers.route')}</th>
                                {productsWithQty.map((p) => (
                                    <th key={p.id} className="border border-black px-2 py-1 text-center whitespace-nowrap">
                                        {p.name_ar}
                                    </th>
                                ))}
                                <th className="border border-black px-2 py-1 text-center">{t('orders.row_total')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {summary.routes.map((routeRow, i) => (
                                <tr key={i}>
                                    <td className="border border-black px-2 py-1 font-medium">{routeRow.name ?? t('orders.no_route')}</td>
                                    {productsWithQty.map((p) => (
                                        <td key={p.id} className="border border-black px-2 py-1 text-center tabular-nums">
                                            {routeRow.products[p.id] ? fmtInt(routeRow.products[p.id]) : ''}
                                        </td>
                                    ))}
                                    <td className="border border-black px-2 py-1 text-center font-bold tabular-nums">{fmtInt(routeRow.total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </>
            )}
        </div>
    );
}
