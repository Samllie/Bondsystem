import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { buildTin, parseTinParts } from '@/lib/tinFormat';
import { useRef } from 'react';

const inputClassName = 'w-16 text-center font-mono tracking-widest sm:w-20';

const suffixInputClassName = 'w-20 text-center font-mono tracking-widest sm:w-24';

export default function TinField({ label, value, onChange, error, required }) {
    const [part1, part2, part3, part4] = parseTinParts(value);
    const part2Ref = useRef(null);
    const part3Ref = useRef(null);
    const part4Ref = useRef(null);

    const updatePart = (index, nextValue) => {
        const maxLength = index === 3 ? 4 : 3;
        const digits = String(nextValue).replace(/\D/g, '').slice(0, maxLength);
        const parts = [part1, part2, part3, part4];
        parts[index] = digits;

        onChange(buildTin(parts[0], parts[1], parts[2], parts[3]));

        if (digits.length === maxLength) {
            if (index === 0) {
                part2Ref.current?.focus();
            } else if (index === 1) {
                part3Ref.current?.focus();
            } else if (index === 2) {
                part4Ref.current?.focus();
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
                <TextInput
                    ref={part4Ref}
                    inputMode="numeric"
                    autoComplete="off"
                    maxLength={4}
                    value={part4}
                    onChange={(event) => updatePart(3, event.target.value)}
                    placeholder="0000"
                    className={suffixInputClassName}
                    aria-label={`${label} fourth segment`}
                />
            </div>
            {error && <InputError message={error} className="mt-1" />}
        </div>
    );
}
