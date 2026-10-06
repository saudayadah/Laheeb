import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

export function EmptyState({ icon: Icon, message, action }: { icon?: LucideIcon; message: string; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">
            {Icon && <Icon className="text-muted-foreground/60 size-10" strokeWidth={1.5} />}
            <p className="text-muted-foreground max-w-sm text-sm">{message}</p>
            {action}
        </div>
    );
}
