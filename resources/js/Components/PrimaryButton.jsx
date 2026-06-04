export default function PrimaryButton({
    className = '',
    disabled,
    children,
    type,
    ...props
}) {
    return (
        <button
            {...props}
            {...(type !== undefined ? { type } : {})}
            className={`btn-primary ${disabled ? 'opacity-60' : ''} ${className}`}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
