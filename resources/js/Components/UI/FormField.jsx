import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export function FormField({ label, error, children, required }) {
    return (
        <div>
            {label && <InputLabel value={required ? `${label} *` : label} />}
            <div className="mt-1">{children}</div>
            {error && <InputError message={error} className="mt-1" />}
        </div>
    );
}

export function TextField({ label, error, required, ...props }) {
    return (
        <FormField label={label} error={error} required={required}>
            <TextInput className="block w-full" {...props} />
        </FormField>
    );
}

export function TextAreaField({ label, error, required, rows = 3, className = '', ...props }) {
    return (
        <FormField label={label} error={error} required={required}>
            <textarea
                rows={rows}
                className={`block w-full rounded-md border-slate-300 shadow-sm focus:border-sterling-gold focus:ring-sterling-gold ${className}`}
                {...props}
            />
        </FormField>
    );
}

export function SelectField({ label, error, required, options = [], ...props }) {
    return (
        <FormField label={label} error={error} required={required}>
            <select
                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-sterling-gold focus:ring-sterling-gold"
                {...props}
            >
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
        </FormField>
    );
}
