import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { type ReactNode } from 'react';

export function Field({
    label,
    htmlFor,
    error,
    optional = false,
    children,
    className,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    optional?: boolean;
    children: ReactNode;
    className?: string;
}) {
    const { t } = useTrans();

    return (
        <div className={`grid gap-1.5 ${className ?? ''}`}>
            <Label htmlFor={htmlFor}>
                {label}
                {optional && <span className="text-muted-foreground ms-1 text-xs font-normal">({t('common.optional')})</span>}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

/** Native select styled to match the Input component, RTL-friendly. */
export function NativeSelect(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
    const { className, ...rest } = props;

    return (
        <select
            {...rest}
            className={`border-input bg-background ring-offset-background focus-visible:ring-ring dark:bg-input/30 h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 ${className ?? ''}`}
        />
    );
}
