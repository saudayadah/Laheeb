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
            className={`border-input bg-background dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 ${className ?? ''}`}
        />
    );
}
