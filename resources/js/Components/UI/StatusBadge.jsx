const colorMap = {
    gray: 'bg-slate-100 text-slate-700',
    amber: 'bg-sterling-gold-50 text-sterling-green-dark',
    blue: 'bg-blue-100 text-blue-800',
    red: 'bg-red-100 text-red-800',
    green: 'bg-emerald-100 text-emerald-800',
    slate: 'bg-slate-200 text-slate-700',
};

export default function StatusBadge({ label, color = 'gray' }) {
    return (
        <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ${colorMap[color] || colorMap.gray}`}>
            {label}
        </span>
    );
}
