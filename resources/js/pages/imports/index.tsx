import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useTrans } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';
import { Download, FileUp, LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';

export default function ImportsIndex({ types }: { types: string[] }) {
    const { t } = useTrans();

    return (
        <AppLayout breadcrumbs={[{ title: t('imports.title'), href: '/imports' }]}>
            <Head title={t('imports.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={t('imports.title')} />
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {types.map((type) => (
                        <ImportCard key={type} type={type} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function ImportCard({ type }: { type: string }) {
    const { t } = useTrans();
    const fileInput = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = () => {
        if (!file) return;
        setUploading(true);
        setError(null);
        router.post(
            route('imports.preview', type),
            { file },
            {
                forceFormData: true,
                onError: (errors) => setError(Object.values(errors)[0] as string),
                onFinish: () => setUploading(false),
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <FileUp className="text-muted-foreground size-4" />
                    {t(`imports.${type}`)}
                </CardTitle>
                <p className="text-muted-foreground text-sm">{t(`imports.${type}_desc`)}</p>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <Button variant="outline" size="sm" asChild>
                    <a href={route('imports.template', type)}>
                        <Download className="size-4" />
                        {t('imports.download_template')}
                    </a>
                </Button>
                <input
                    ref={fileInput}
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    aria-label={t('imports.choose_file')}
                    className="text-muted-foreground file:bg-muted file:text-foreground w-full cursor-pointer rounded-md border p-2 text-sm file:me-3 file:cursor-pointer file:rounded file:border-0 file:px-3 file:py-1"
                    onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                />
                {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
                <Button size="sm" onClick={upload} disabled={!file || uploading}>
                    {uploading && <LoaderCircle className="size-4 animate-spin" />}
                    {t('imports.upload')}
                </Button>
            </CardContent>
        </Card>
    );
}
