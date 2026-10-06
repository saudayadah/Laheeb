import { NativeSelect } from '@/components/field';
import { Button } from '@/components/ui/button';
import { fmtInt } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Printer } from 'lucide-react';

interface LoadingProps {
    date: string;
    weekday: number;
    routes: { id: number; name: string; driver: string | null }[];
    routeId: number | null;
    sheet: {
        products: { id: number; name_ar: string; size_cm: number | null }[];
        rows: { customer: { id: number; code: string; name: string; stop_sequence: number }; qtys: Record<number, number>; total: number }[];
        totals: Record<number, number>;
        grandTotal: number;
    } | null;
}

export default function LoadingSheet({ date, weekday, routes, routeId, sheet }: LoadingProps) {
    const { t } = useTrans();

    const currentRoute = routes.find((r) => r.id === routeId);
    const productsWithQty = sheet ? sheet.products.filter((p) => (sheet.totals[p.id] ?? 0) > 0) : [];

    return (
        <div className="mx-auto max-w-5xl p-6 print:p-0">
            <Head title={t('orders.loading_sheet')} />

            <div className="mb-4 flex flex-wrap items-center gap-2 print:hidden">
                <Button variant="outline" size="sm" asChild>
                    <Link href={route('orders.grid', { date })}>
                        <ArrowRight className="size-4" /> {t('common.back')}
                    </Link>
                </Button>
                <NativeSelect
                    className="w-52"
                    value={routeId ?? ''}
                    onChange={(e) => router.get(route('orders.loading'), { date, route_id: e.target.value }, { preserveState: false })}
                >
                    {routes.map((r) => (
                        <option key={r.id} value={r.id}>
                            {r.name}
                        </option>
                    ))}
                </NativeSelect>
                <Button size="sm" onClick={() => window.print()}>
                    <Printer className="size-4" /> {t('orders.print')}
                </Button>
            </div>

            <div className="mb-6 text-center">
                <h1 className="text-xl font-bold">
                    {t('orders.loading_sheet')} — {currentRoute?.name}
                </h1>
                <p className="text-sm">
                    {t(`weekday.${weekday}`)} — <span dir="ltr">{date}</span>
                    {currentRoute?.driver && (
                        <span className="ms-4">
                            {t('orders.driver')}: {currentRoute.driver}
                        </span>
                    )}
                </p>
            </div>

            {!sheet || sheet.rows.length === 0 ? (
                <p className="text-muted-foreground py-16 text-center">{t('common.no_results')}</p>
            ) : (
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th className="border border-black px-2 py-1 text-center">#</th>
                            <th className="border border-black px-2 py-1 text-start">{t('orders.customer')}</th>
                            {productsWithQty.map((p) => (
                                <th key={p.id} className="border border-black px-2 py-1 text-center whitespace-nowrap">
                                    {p.name_ar}
                                </th>
                            ))}
                            <th className="border border-black px-2 py-1 text-center">{t('orders.row_total')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sheet.rows.map((row, i) => (
                            <tr key={row.customer.id}>
                                <td className="border border-black px-2 py-1 text-center tabular-nums">{i + 1}</td>
                                <td className="border border-black px-2 py-1">
                                    <span className="font-medium">{row.customer.name}</span>
                                    <span className="text-muted-foreground ms-2 text-xs tabular-nums">({row.customer.code})</span>
                                </td>
                                {productsWithQty.map((p) => (
                                    <td key={p.id} className="border border-black px-2 py-1 text-center tabular-nums">
                                        {row.qtys[p.id] ? fmtInt(row.qtys[p.id]) : ''}
                                    </td>
                                ))}
                                <td className="border border-black px-2 py-1 text-center font-bold tabular-nums">{fmtInt(row.total)}</td>
                            </tr>
                        ))}
                        <tr>
                            <td colSpan={2} className="border border-black px-2 py-1 font-bold">
                                {t('orders.grand_total')}
                            </td>
                            {productsWithQty.map((p) => (
                                <td key={p.id} className="border border-black px-2 py-1 text-center font-bold tabular-nums">
                                    {fmtInt(sheet.totals[p.id] ?? 0)}
                                </td>
                            ))}
                            <td className="border border-black px-2 py-1 text-center font-bold tabular-nums">{fmtInt(sheet.grandTotal)}</td>
                        </tr>
                    </tbody>
                </table>
            )}
        </div>
    );
}
