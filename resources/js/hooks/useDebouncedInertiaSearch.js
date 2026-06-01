import { visitTable } from '@/lib/visitTable';
import { useCallback, useEffect, useRef, useState } from 'react';

export default function useDebouncedInertiaSearch({
    initialSearch = '',
    url,
    buildParams,
    only = [],
    delay = 300,
}) {
    const inputRef = useRef(null);
    const [isSearching, setIsSearching] = useState(false);
    const buildParamsRef = useRef(buildParams);
    const debounceTimerRef = useRef(null);

    buildParamsRef.current = buildParams;

    useEffect(() => {
        const input = inputRef.current;

        if (! input || document.activeElement === input) {
            return;
        }

        input.value = initialSearch ?? '';
    }, [initialSearch]);

    const runSearch = useCallback(() => {
        const value = inputRef.current?.value ?? '';

        setIsSearching(true);

        visitTable(url, buildParamsRef.current(value), only, {
            inputRef,
            onFinish: () => setIsSearching(false),
        });
    }, [url, only]);

    const onInput = useCallback(() => {
        clearTimeout(debounceTimerRef.current);
        debounceTimerRef.current = setTimeout(runSearch, delay);
    }, [delay, runSearch]);

    useEffect(() => () => clearTimeout(debounceTimerRef.current), []);

    const getValue = useCallback(() => inputRef.current?.value ?? '', []);

    return { inputRef, isSearching, onInput, getValue, defaultSearch: initialSearch ?? '' };
}
