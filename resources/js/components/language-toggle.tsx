import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';

export function LanguageToggle() {
    const { t, locale } = useTrans();

    const switchTo = locale === 'ar' ? 'en' : 'ar';

    return (
        <Button variant="ghost" size="sm" onClick={() => router.post(route('locale.update'), { locale: switchTo })} title={t('lang.switch')}>
            <Languages className="size-4" />
            <span className="hidden sm:inline">{t('lang.switch')}</span>
        </Button>
    );
}
