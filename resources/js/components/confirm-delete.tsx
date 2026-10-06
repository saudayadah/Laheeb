import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useTrans } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

export function ConfirmDelete({ url, description }: { url: string; description?: string }) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);

    const destroy = () => {
        router.delete(url, {
            preserveScroll: true,
            onFinish: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-destructive size-8">
                    <Trash2 className="size-4" />
                    <span className="sr-only">{t('common.delete')}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('common.confirm_delete_title')}</DialogTitle>
                    <DialogDescription>{description ?? t('common.confirm_delete_text')}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        {t('common.cancel')}
                    </Button>
                    <Button variant="destructive" onClick={destroy}>
                        {t('common.delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
