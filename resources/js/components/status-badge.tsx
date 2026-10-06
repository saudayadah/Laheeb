import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/lib/i18n';

export function ActiveBadge({ active }: { active: boolean }) {
    const { t } = useTrans();

    return (
        <Badge variant={active ? 'secondary' : 'outline'} className={active ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'}>
            {active ? t('common.active') : t('common.inactive')}
        </Badge>
    );
}

export function PaymentTermBadge({ term }: { term: string }) {
    const { t } = useTrans();

    return (
        <Badge
            variant="outline"
            className={term === 'credit' ? 'border-amber-300 text-amber-700 dark:border-amber-700 dark:text-amber-400' : 'text-muted-foreground'}
        >
            {t(`customers.term.${term}`)}
        </Badge>
    );
}
