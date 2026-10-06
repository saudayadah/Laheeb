import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { useTrans } from '@/lib/i18n';
import { type ImportRow } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, LoaderCircle, XCircle } from 'lucide-react';
import { useState } from 'react';

interface ImportPreviewProps {
    type: string;
    token: string;
    headings: string[];
    rows: ImportRow[];
    validCount: number;
    errorCount: number;
}

export default function ImportPreview({ type, token, headings, rows, validCount, errorCount }: ImportPreviewProps) {
    const { t } = useTrans();
    const [committing, setCommitting] = useState(false);

    const commit = () => {
        setCommitting(true);
        router.post(route('imports.commit', type), { token }, { onFinish: () => setCommitting(false) });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('imports.title'), href: '/imports' },
                { title: t('imports.preview_title'), href: '#' },
            ]}
        >
            <Head title={t('imports.preview_title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={`${t('imports.preview_title')} — ${t(`imports.${type}`)}`}
                    actions={
                        <div className="flex items-center gap-2">
                            <Button variant="outline" asChild>
                                <Link href={route('imports.index')}>{t('common.back')}</Link>
                            </Button>
                            <Button onClick={commit} disabled={validCount === 0 || committing}>
                                {committing && <LoaderCircle className="size-4 animate-spin" />}
                                {t('imports.commit')}
                            </Button>
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Badge variant="secondary" className="gap-1 text-emerald-700 dark:text-emerald-400">
                        <CheckCircle2 className="size-3.5" />
                        {t('imports.valid_rows')}: {validCount}
                    </Badge>
                    <Badge variant="secondary" className={errorCount > 0 ? 'gap-1 text-red-700 dark:text-red-400' : 'gap-1'}>
                        <XCircle className="size-3.5" />
                        {t('imports.error_rows')}: {errorCount}
                    </Badge>
                    {errorCount > 0 && <span className="text-muted-foreground text-sm">{t('imports.commit_valid_only')}</span>}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>#</TableHead>
                                {headings.map((h) => (
                                    <TableHead key={h}>{h}</TableHead>
                                ))}
                                <TableHead>{t('imports.errors')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.row} className={row.errors.length > 0 ? 'bg-red-50/50 dark:bg-red-950/20' : ''}>
                                    <TableCell className="text-muted-foreground tabular-nums">{row.row}</TableCell>
                                    {headings.map((h) => (
                                        <TableCell key={h} className="max-w-40 truncate">
                                            {row.data[h] ?? ''}
                                        </TableCell>
                                    ))}
                                    <TableCell>
                                        {row.errors.length > 0 && (
                                            <ul className="list-inside text-xs text-red-600 dark:text-red-400">
                                                {row.errors.map((error, i) => (
                                                    <li key={i}>{error}</li>
                                                ))}
                                            </ul>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
