import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import axios from 'axios';
import { useCallback, useEffect, useRef, useState } from 'react';

export default function EditableCombobox({
    label,
    value,
    textValue,
    onChange,
    onTextChange,
    searchUrl,
    placeholder = 'Type or select…',
    error,
    required = false,
    initialOption = null,
    minChars = 0,
    onOptionSelect = null,
    localOptions = null,
}) {
    const [options, setOptions] = useState(initialOption ? [initialOption] : []);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const containerRef = useRef(null);
    const requestIdRef = useRef(0);
    const debounceTimerRef = useRef(null);

    const fetchOptions = useCallback(async (search) => {
        if (Array.isArray(localOptions)) {
            const normalizedSearch = search.trim().toLowerCase();
            const filteredOptions = localOptions.filter((option) => {
                const labelText = String(option.label || option.company_name || '').toLowerCase();

                return normalizedSearch === '' || labelText.includes(normalizedSearch);
            });

            setOptions(filteredOptions);
            setLoading(false);

            return;
        }

        const requestId = ++requestIdRef.current;
        setLoading(true);

        try {
            const response = await axios.get(searchUrl, { params: { search } });

            if (requestId !== requestIdRef.current) {
                return;
            }

            setOptions(response.data.data || []);
        } catch {
            if (requestId !== requestIdRef.current) {
                return;
            }

            setOptions([]);
        } finally {
            if (requestId === requestIdRef.current) {
                setLoading(false);
            }
        }
    }, [searchUrl, localOptions]);

    const scheduleFetch = useCallback(
        (search) => {
            clearTimeout(debounceTimerRef.current);
            debounceTimerRef.current = setTimeout(() => {
                if (search.length >= minChars) {
                    fetchOptions(search);
                }
            }, 300);
        },
        [minChars, fetchOptions],
    );

    useEffect(() => () => clearTimeout(debounceTimerRef.current), []);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (initialOption) {
            setOptions([initialOption]);

            return;
        }

        if (Array.isArray(localOptions) && localOptions.length > 0) {
            setOptions(localOptions);
        }
    }, [initialOption, localOptions]);

    const handleSelect = (option) => {
        onChange(option.id);
        onTextChange(option.label || option.company_name || '');
        onOptionSelect?.(option);
        setOpen(false);
    };

    const handleInputChange = (event) => {
        const next = event.target.value;
        onTextChange(next);
        setOpen(true);
        scheduleFetch(next);
    };

    const handleFocus = () => {
        setOpen(true);

        if (Array.isArray(localOptions)) {
            fetchOptions(textValue ?? '');

            return;
        }

        scheduleFetch(textValue ?? '');
    };

    return (
        <div ref={containerRef} className="relative">
            {label && <InputLabel value={required ? `${label} *` : label} />}
            <div className="relative">
                <input
                    type="text"
                    value={textValue ?? ''}
                    onChange={handleInputChange}
                    onFocus={handleFocus}
                    placeholder={placeholder}
                    className={`mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-sterling-gold focus:ring-sterling-gold ${loading ? 'pr-24' : ''}`}
                    autoComplete="off"
                />
                {loading && (
                    <span className="pointer-events-none absolute inset-y-0 right-3 mt-1 flex items-center text-xs text-sterling-green">
                        Searching…
                    </span>
                )}
            </div>
            {open && (
                <div className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                    {!loading && options.length === 0 && (
                        <p className="px-3 py-2 text-sm text-slate-500">No results found.</p>
                    )}
                    {!loading &&
                        options.map((option) => (
                            <button
                                key={option.id}
                                type="button"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => handleSelect(option)}
                                className={`block w-full px-3 py-2 text-left text-sm hover:bg-sterling-gold-50 ${
                                    String(value) === String(option.id) ? 'bg-sterling-gold-50 font-medium' : 'text-slate-700'
                                }`}
                            >
                                {option.label || option.company_name}
                            </button>
                        ))}
                </div>
            )}
            {error && <InputError message={error} className="mt-1" />}
        </div>
    );
}
