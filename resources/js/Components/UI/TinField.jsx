import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { buildTin, parseTinParts, TIN_SUFFIX } from '@/lib/tinFormat';
import { useRef } from 'react';

const inputClassName =
    'w-16 text-center font-mono tracking-widest sm:w-20';

export default function TinField({ label, value, onChange, error, required }) {
    const [part1, part2, part3] = parseTinParts(value);
    const part2Ref = useRef(null);
    const part3Ref = useRef(null);

    const updatePart = (index, nextValue) => {
        const digits = String(nextValue).replace(/\D/g, '').slice(0, 3);
        const parts = [part1, part2, part3];
        parts[index] = digits;

        onChange(buildTin(parts[0], parts[1], parts[2]));

        if (digits.length === 3) {
            if (index === 0) {
                part2Ref.current?.focus();
            } else if (index === 1) {
                part3Ref.current?.focus();
            }
        }
    };

    return (
        <div>
            {label && <InputLabel value={required ? `${label} *` : label} />}
            <div className="mt-1 flex flex-wrap items-center gap-1.5">
                <TextInput
                    inputMode="numeric"
                    autoComplete="off"
                    maxLength={3}
                    value={part1}
                    onChange={(event) => updatePart(0, event.target.value)}
                    placeholder="000"
                    className={inputClassName}
                    aria-label={`${label} first segment`}
                />
                <span className="font-mono text-slate-500">-</span>
                <TextInput
                    ref={part2Ref}
                    inputMode="numeric"
                    autoComplete="off"
                    maxLength={3}
                    value={part2}
                    onChange={(event) => updatePart(1, event.target.value)}
                    placeholder="000"
                    className={inputClassName}
                    aria-label={`${label} second segment`}
                />
                <span className="font-mono text-slate-500">-</span>
                <TextInput
                    ref={part3Ref}
                    inputMode="numeric"
                    autoComplete="off"
                    maxLength={3}
                    value={part3}
                    onChange={(event) => updatePart(2, event.target.value)}
                    placeholder="000"
                    className={inputClassName}
                    aria-label={`${label} third segment`}
                />
                <span className="font-mono text-slate-500">-</span>
                <span className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm tracking-widest text-slate-600">
                    {TIN_SUFFIX}
                </span>
            </div>
            {error && <InputError message={error} className="mt-1" />}
        </div>
    );
}
