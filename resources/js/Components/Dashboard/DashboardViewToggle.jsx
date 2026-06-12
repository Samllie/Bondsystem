import { router } from '@inertiajs/react';

export default function DashboardViewToggle({ filters }) {
    const setView = (view) => {
        router.get(
            route('dashboard'),
            {
                date_from: filters.date_from || undefined,
                date_to: filters.date_to || undefined,
                status: filters.status || undefined,
                bond_type_id: filters.bond_type_id || undefined,
                branch_id: filters.branch_id || undefined,
                view,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const currentView = filters.view ?? 'overview';

    return (
        <div className="no-print mb-6 flex flex-wrap items-center gap-2">
            <span className="text-sm font-medium text-slate-600">View:</span>
            <button
                type="button"
                onClick={() => setView('overview')}
                className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                    currentView === 'overview'
                        ? 'bg-sterling-green text-white'
                        : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-sterling-green-50'
                }`}
            >
                Overview
            </button>
            <button
                type="button"
                onClick={() => setView('table')}
                className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                    currentView === 'table'
                        ? 'bg-sterling-green text-white'
                        : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-sterling-green-50'
                }`}
            >
                Table View
            </button>
        </div>
    );
}
