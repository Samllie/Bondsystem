export default function StatCard({ label, value, icon, color = 'blue' }) {
    const colors = {
        blue: 'bg-sterling-green-50 text-sterling-green',
        amber: 'bg-sterling-gold-50 text-sterling-gold-dark',
        green: 'bg-sterling-green-50 text-sterling-green-light',
        slate: 'bg-slate-100 text-slate-600',
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium text-slate-500">{label}</p>
                    <p className="mt-2 text-3xl font-bold text-sterling-green-darker">{value}</p>
                </div>
                {icon && <div className={`rounded-lg p-2.5 ${colors[color]}`}>{icon}</div>}
            </div>
        </div>
    );
}
