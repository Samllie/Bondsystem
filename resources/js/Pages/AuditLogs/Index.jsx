import Card, { CardBody } from '@/Components/UI/Card';
import Pagination from '@/Components/UI/Pagination';
import { visitTable } from '@/lib/visitTable';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

const TABLE_PROPS = ['logs', 'filters'];

function formatTimestamp(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export default function Index({ logs, filters, userOptions, actionOptions, entityTypeOptions }) {
    const url = route('audit-logs.index');

    const applyFilters = (extra = {}) => {
        const nextFilters = {
            user_id: filters.user_id,
            action: filters.action,
            entity_type: filters.entity_type,
            date_from: filters.date_from,
            date_to: filters.date_to,
            ...extra,
        };

        visitTable(
            url,
            {
                user_id: nextFilters.user_id || undefined,
                action: nextFilters.action || undefined,
                entity_type: nextFilters.entity_type || undefined,
                date_from: nextFilters.date_from || undefined,
                date_to: nextFilters.date_to || undefined,
            },
            TABLE_PROPS,
        );
    };

    return (
        <AppLayout title="Audit Logs">
            <Head title="Audit Logs" />

            <Card className="mb-4">
                <CardBody>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">User</label>
                            <select
                                value={filters.user_id ?? ''}
                                onChange={(e) =>
                                    applyFilters({ user_id: e.target.value ? Number(e.target.value) : undefined })
                                }
                                className="w-full rounded-md border-slate-300 text-sm"
                            >
                                <option value="">All users</option>
                                {userOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">Action</label>
                            <select
                                value={filters.action ?? ''}
                                onChange={(e) => applyFilters({ action: e.target.value || undefined })}
                                className="w-full rounded-md border-slate-300 text-sm"
                            >
                                <option value="">All actions</option>
                                {actionOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">Entity Type</label>
                            <select
                                value={filters.entity_type ?? ''}
                                onChange={(e) => applyFilters({ entity_type: e.target.value || undefined })}
                                className="w-full rounded-md border-slate-300 text-sm"
                            >
                                <option value="">All entity types</option>
                                {entityTypeOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">Date From</label>
                            <input
                                type="date"
                                value={filters.date_from ?? ''}
                                onChange={(e) => applyFilters({ date_from: e.target.value || undefined })}
                                className="w-full rounded-md border-slate-300 text-sm"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-500">Date To</label>
                            <input
                                type="date"
                                value={filters.date_to ?? ''}
                                onChange={(e) => applyFilters({ date_to: e.target.value || undefined })}
                                className="w-full rounded-md border-slate-300 text-sm"
                            />
                        </div>
                    </div>
                </CardBody>
            </Card>

            <Card>
                <CardBody className="overflow-x-auto p-0">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Timestamp</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">User</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Action</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Entity Type</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Entity ID</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Description</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">IP Address</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {logs.data.map((log) => (
                                <tr key={log.id} className="hover:bg-slate-50">
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">
                                        {formatTimestamp(log.created_at)}
                                    </td>
                                    <td className="px-4 py-3 text-slate-900">{log.user?.name ?? 'System'}</td>
                                    <td className="px-4 py-3 text-slate-600">{log.action}</td>
                                    <td className="px-4 py-3 text-slate-600">{log.entity_type}</td>
                                    <td className="px-4 py-3 text-slate-600">{log.entity_id ?? '—'}</td>
                                    <td className="max-w-md px-4 py-3 text-slate-600">{log.description ?? '—'}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{log.ip_address ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {logs.data.length === 0 && (
                        <p className="px-6 py-8 text-center text-sm text-slate-500">No audit logs found.</p>
                    )}
                </CardBody>
            </Card>

            <Pagination links={logs.links} meta={logs.meta} />
        </AppLayout>
    );
}
