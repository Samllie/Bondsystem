import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';

const phpBytes = (bytes) => {
    if (!bytes) {
        return '0 B';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
};

const statusColors = {
    pending: 'amber',
    running: 'blue',
    completed: 'green',
    failed: 'red',
};

export default function BackupsIndex({
    backups,
    filters,
    typeOptions,
    statusOptions,
    restoreInstructions,
    scheduleExamples,
    retentionDays,
}) {
    const createBackup = (backupType) => {
        router.post(route('backups.store'), { backup_type: backupType }, { preserveScroll: true });
    };

    const applyFilter = (key, value) => {
        router.get(
            route('backups.index'),
            {
                backup_type: key === 'backup_type' ? value || undefined : filters.backup_type || undefined,
                backup_status: key === 'backup_status' ? value || undefined : filters.backup_status || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout
            title="Backup Management"
            actions={
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => createBackup('database')}
                        className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Database Backup
                    </button>
                    <button
                        type="button"
                        onClick={() => createBackup('files')}
                        className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Files Backup
                    </button>
                    <button
                        type="button"
                        onClick={() => createBackup('full')}
                        className="rounded-lg bg-sterling-gold px-3 py-2 text-sm font-semibold text-sterling-green-darker hover:bg-sterling-gold-light"
                    >
                        Full Backup
                    </button>
                </div>
            }
        >
            <Head title="Backup Management" />

            <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <p className="font-semibold">Manual restore only</p>
                <p className="mt-1">
                    Download backups and follow the restore checklist. Automatic restore is intentionally disabled to
                    prevent accidental data loss.
                </p>
            </div>

            <div className="mb-4 flex flex-wrap gap-3">
                <select
                    value={filters.backup_type ?? ''}
                    onChange={(e) => applyFilter('backup_type', e.target.value)}
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                >
                    <option value="">All Types</option>
                    {typeOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <select
                    value={filters.backup_status ?? ''}
                    onChange={(e) => applyFilter('backup_status', e.target.value)}
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                >
                    <option value="">All Statuses</option>
                    {statusOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50">
                        <tr>
                            {['Date', 'Type', 'Filename', 'Size', 'Status', 'Created By', 'Verified', 'Actions'].map(
                                (heading) => (
                                    <th
                                        key={heading}
                                        className="px-4 py-3 text-left text-xs font-medium text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ),
                            )}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {backups.data.map((backup) => (
                            <tr key={backup.id} className="hover:bg-slate-50">
                                <td className="px-4 py-3 text-slate-600">
                                    {backup.started_at
                                        ? new Date(backup.started_at).toLocaleString('en-PH')
                                        : '—'}
                                </td>
                                <td className="px-4 py-3 text-slate-700">{backup.backup_type_label}</td>
                                <td className="px-4 py-3 font-mono text-xs text-slate-800">{backup.filename}</td>
                                <td className="px-4 py-3 text-slate-700">{phpBytes(backup.file_size)}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge
                                        label={backup.backup_status_label}
                                        color={statusColors[backup.backup_status] ?? 'slate'}
                                    />
                                </td>
                                <td className="px-4 py-3 text-slate-700">{backup.created_by ?? 'System'}</td>
                                <td className="px-4 py-3 text-slate-700">
                                    {backup.verification_passed === true && 'Passed'}
                                    {backup.verification_passed === false && 'Failed'}
                                    {backup.verification_passed === null && '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-2">
                                        <Link
                                            href={route('backups.show', backup.id)}
                                            className="text-sterling-green hover:underline"
                                        >
                                            Details
                                        </Link>
                                        {backup.can_download && (
                                            <a
                                                href={route('backups.download', backup.id)}
                                                className="text-slate-600 hover:text-sterling-green hover:underline"
                                            >
                                                Download
                                            </a>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {backups.data.length === 0 && (
                    <p className="px-6 py-8 text-center text-sm text-slate-500">No backups found.</p>
                )}
            </div>

            <Pagination links={backups.links} meta={backups.meta} />

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Restore checklist</h2>
                    <ol className="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                        {restoreInstructions.map((step) => (
                            <li key={step}>{step}</li>
                        ))}
                    </ol>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Retention & scheduling</h2>
                    <p className="mt-3 text-sm text-slate-700">
                        Completed backups older than {retentionDays} days are removed by{' '}
                        <code className="rounded bg-slate-100 px-1">php artisan backups:cleanup</code>. Failed backups
                        are kept until manually deleted.
                    </p>
                    <ul className="mt-4 space-y-2 text-sm text-slate-600">
                        {Object.entries(scheduleExamples).map(([key, command]) => (
                            <li key={key}>
                                <span className="font-medium text-slate-700">{key.replaceAll('_', ' ')}:</span>{' '}
                                <code className="rounded bg-slate-100 px-1">{command}</code>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AppLayout>
    );
}
