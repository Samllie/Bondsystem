export default function Card({ children, className = '' }) {
    return (
        <div className={`rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>
            {children}
        </div>
    );
}

export function CardHeader({ title, action }) {
    return (
        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h3 className="font-semibold text-sterling-green">{title}</h3>
            {action}
        </div>
    );
}

export function CardBody({ children, className = '' }) {
    return <div className={`p-5 ${className}`}>{children}</div>;
}
