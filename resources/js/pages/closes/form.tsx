import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { CheckCheck, LoaderCircle } from 'lucide-react';
import { useMemo, useState } from 'react';

interface CloseFormProps {
    type: 'driver' | 'counter';
    closeableId: number;
    driverName: string | null;
    date: string;
    expected: string;
    breakdown: { cash_sales: string; collections: string; expenses: string };
    denominations: number[];
    existing: {
        id: number;
        status: string;
        counted: string;
        variance: string;
        notes: string | null;
        counts: Record<string, number>;
    } | null;
    canApprove: boolean;
}

export default function CloseForm({ type, closeableId, driverName, date, expected, breakdown, denominations, existing, canApprove }: CloseFormProps) {
    const { t } = useTrans();
    const approved = existing?.status === 'approved';

    const [counts, setCounts] = useState<Record<number, string>>(() =>
        Object.fromEntries(denominations.map((d) => [d, existing?.counts?.[d] ? String(existing.counts[d]) : ''])),
    );
    const [notes, setNotes] = useState(existing?.notes ?? '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const counted = useMemo(() => denominations.reduce((sum, d) => sum + d * (parseInt(counts[d] ?? '', 10) || 0), 0), [denominations, counts]);
    const variance = counted - parseFloat(expected);

    const submit = () => {
        setSaving(true);
        setError(null);
        router.post(
            route('closes.store'),
            {
                type,
                id: type === 'driver' ? closeableId : null,
                date,
                counts: Object.fromEntries(denominations.map((d) => [d, parseInt(counts[d] ?? '', 10) || 0])),
                notes: notes || null,
            },
            {
                onError: (e) => setError(Object.values(e)[0] as string),
                onFinish: () => setSaving(false),
            },
        );
    };

    const approve = () => {
        if (!existing) return;
        router.post(route('closes.approve', existing.id), {}, { preserveScroll: true });
    };

    const title = type === 'driver' ? `${t('closes.new_driver')} — ${driverName ?? ''}` : t('closes.new_counter');

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('closes.title'), href: '/closes' },
                { title, href: '#' },
            ]}
        >
            <Head title={t('closes.title')} />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <PageHeader
                    title={title}
                    description={date}
                    actions={
                        approved ? (
                            <Badge variant="secondary" className="text-emerald-700 dark:text-emerald-400">
                                {t('closes.status.approved')}
                            </Badge>
                        ) : existing && canApprove ? (
                            <Button onClick={approve}>
                                <CheckCheck className="size-4" /> {t('closes.approve')}
                            </Button>
                        ) : null
                    }
                />

                {/* What we expect and why */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="border-primary/30 bg-primary/10 dark:bg-primary/15 rounded-xl border p-3 text-center">
                        <div className="text-xl font-bold tabular-nums">{fmtAmount(expected)}</div>
                        <div className="text-muted-foreground text-xs">{t('closes.expected')}</div>
                    </div>
                    <div className="rounded-xl border p-3 text-center">
                        <div className="text-lg font-semibold tabular-nums">{fmtAmount(breakdown.cash_sales)}</div>
                        <div className="text-muted-foreground text-xs">{t('closes.cash_sales')}</div>
                    </div>
                    <div className="rounded-xl border p-3 text-center">
                        <div className="text-lg font-semibold tabular-nums">{fmtAmount(breakdown.collections)}</div>
                        <div className="text-muted-foreground text-xs">{t('closes.collections')}</div>
                    </div>
                    <div className="rounded-xl border p-3 text-center">
                        <div className="text-lg font-semibold tabular-nums">{fmtAmount(breakdown.expenses)}</div>
                        <div className="text-muted-foreground text-xs">{t('closes.expenses')}</div>
                    </div>
                </div>

                {/* Denomination grid */}
                <div className="rounded-xl border">
                    <div className="text-muted-foreground grid grid-cols-3 gap-2 border-b p-2 text-xs font-medium">
                        <span>{t('closes.denomination')}</span>
                        <span className="text-center">{t('closes.count')}</span>
                        <span className="text-end">{t('common.total')}</span>
                    </div>
                    {denominations.map((denomination) => {
                        const count = parseInt(counts[denomination] ?? '', 10) || 0;
                        return (
                            <div key={denomination} className="grid grid-cols-3 items-center gap-2 border-b p-2 last:border-0">
                                <span className="text-lg font-bold tabular-nums">{denomination}</span>
                                <Input
                                    type="text"
                                    inputMode="numeric"
                                    dir="ltr"
                                    disabled={approved}
                                    aria-label={`${t('closes.count')} ${denomination}`}
                                    className="h-11 text-center text-lg tabular-nums"
                                    value={counts[denomination] ?? ''}
                                    onFocus={(e) => e.target.select()}
                                    onChange={(e) => setCounts((c) => ({ ...c, [denomination]: e.target.value.replace(/[^\d]/g, '') }))}
                                />
                                <span className="text-end tabular-nums">{count > 0 ? fmtAmount(denomination * count) : ''}</span>
                            </div>
                        );
                    })}
                </div>

                {/* Counted vs expected */}
                <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-xl border p-4 text-center">
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(counted)}</div>
                        <div className="text-muted-foreground text-xs">{t('closes.counted')}</div>
                    </div>
                    <div
                        className={`rounded-xl border p-4 text-center ${
                            variance < -0.004
                                ? 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/40'
                                : variance > 0.004
                                  ? 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40'
                                  : 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40'
                        }`}
                    >
                        <div className="text-2xl font-bold tabular-nums">{fmtAmount(variance)}</div>
                        <div className="text-muted-foreground text-xs">{t('closes.variance')}</div>
                    </div>
                </div>

                {type === 'driver' && <p className="text-muted-foreground text-xs">{t('closes.shortage_note')}</p>}

                {error && (
                    <div className="rounded-lg border border-red-300 bg-red-100/60 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/20 dark:text-red-400">
                        {error}
                    </div>
                )}

                {!approved && (
                    <>
                        <Input
                            aria-label={t('common.notes')}
                            placeholder={t('common.notes')}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                        />
                        <Button size="lg" className="h-12" onClick={submit} disabled={saving}>
                            {saving && <LoaderCircle className="size-4 animate-spin" />}
                            {t('closes.submit')}
                        </Button>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
