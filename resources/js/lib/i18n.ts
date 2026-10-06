import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Translation + permission helpers backed by the shared Inertia props.
 */
export function useTrans() {
    const { translations, locale } = usePage<SharedData>().props;

    const t = (key: string, replacements?: Record<string, string | number>): string => {
        let value = translations[key] ?? key;

        if (replacements) {
            for (const [name, replacement] of Object.entries(replacements)) {
                value = value.replaceAll(`:${name}`, String(replacement));
            }
        }

        return value;
    };

    return { t, locale };
}

export function useCan() {
    const { auth } = usePage<SharedData>().props;

    return (permission: string): boolean => {
        if (!auth.user) return false;
        if (auth.user.roles.includes('owner')) return true;

        return auth.user.permissions.includes(permission);
    };
}
