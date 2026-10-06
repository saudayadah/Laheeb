import { useTrans } from '@/lib/i18n';
import { Wheat } from 'lucide-react';

export default function AppLogo() {
    const { t } = useTrans();

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                <Wheat className="size-5" strokeWidth={2.2} />
            </div>
            <div className="ms-1 grid flex-1 text-start text-sm">
                <span className="mb-0.5 truncate leading-none font-bold">{t('app.name')}</span>
            </div>
        </>
    );
}
