import { Input } from '@/components/ui/input';
import { useTrans } from '@/lib/i18n';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export function SearchInput({ value, onChange, placeholder }: { value: string; onChange: (value: string) => void; placeholder?: string }) {
    const { t } = useTrans();
    const [text, setText] = useState(value);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => onChange(text), 350);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [text]);

    return (
        <div className="relative w-full max-w-xs">
            <Search className="text-muted-foreground absolute start-2.5 top-1/2 size-4 -translate-y-1/2" />
            <Input
                value={text}
                onChange={(e) => setText(e.target.value)}
                placeholder={placeholder ?? t('common.search')}
                aria-label={placeholder ?? t('common.search')}
                className="ps-8"
            />
        </div>
    );
}
