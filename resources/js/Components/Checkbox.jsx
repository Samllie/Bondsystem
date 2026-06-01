export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-slate-300 text-sterling-green shadow-sm focus:ring-sterling-gold ' +
                className
            }
        />
    );
}
