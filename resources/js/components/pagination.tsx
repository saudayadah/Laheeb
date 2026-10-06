import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { type Paginated } from '@/types';
import { Link } from '@inertiajs/react';

export function Pagination({ paginator }: { paginator: Paginated<unknown> }) {
    const { t } = useTrans();

    if (paginator.last_page <= 1) return null;

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-muted-foreground text-sm">
                {t('common.showing', {
                    from: paginator.from ?? 0,
                    to: paginator.to ?? 0,
                    total: paginator.total,
                })}
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {paginator.links.map((link, i) => {
                    const label = link.label.replace('&laquo;', '«').replace('&raquo;', '»');

                    if (link.url === null) {
                        return (
                            <span key={i} className="text-muted-foreground px-2 py-1 text-sm">
                                {label}
                            </span>
                        );
                    }

                    return (
                        <Button key={i} variant={link.active ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={link.url} preserveScroll preserveState>
                                {label}
                            </Link>
                        </Button>
                    );
                })}
            </div>
        </div>
    );
}
