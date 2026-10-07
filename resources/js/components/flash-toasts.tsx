import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Transient success / error banner fed by the Laravel session flash.
 */
export function FlashToasts() {
    const { flash } = usePage<SharedData>().props;
    const [visible, setVisible] = useState(false);

    const message = flash?.success || flash?.error;
    const isError = Boolean(flash?.error);

    useEffect(() => {
        if (!message) return;
        setVisible(true);
        const timer = setTimeout(() => setVisible(false), 4000);
        return () => clearTimeout(timer);
    }, [message, flash]);

    if (!message || !visible) return null;

    return (
        <div className="fixed start-1/2 bottom-6 z-50 -translate-x-1/2 rtl:translate-x-1/2" role={isError ? 'alert' : 'status'}>
            <div
                className={`flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm shadow-lg ${
                    isError
                        ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                }`}
            >
                {isError ? <XCircle className="size-4 shrink-0" /> : <CheckCircle2 className="size-4 shrink-0" />}
                <span>{message}</span>
            </div>
        </div>
    );
}
