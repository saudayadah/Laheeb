import { useTrans } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { Wheat } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    const { t } = useTrans();

    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="bg-card w-full max-w-sm rounded-2xl border p-8 shadow-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={route('home')} className="flex flex-col items-center gap-3 font-medium">
                            <div className="bg-primary text-primary-foreground flex size-14 items-center justify-center rounded-2xl">
                                <Wheat className="size-8" strokeWidth={2.2} />
                            </div>
                            <span className="text-lg font-bold">{t('app.name')}</span>
                        </Link>

                        <div className="space-y-1 text-center">
                            <h1 className="text-xl font-semibold">{title}</h1>
                            <p className="text-muted-foreground text-center text-sm">{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
