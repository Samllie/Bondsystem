import BackLink from '@/Components/UI/BackLink';
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

function Detail({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 text-sm text-slate-900">{value || '—'}</dd>
        </div>
    );
}

export default function BackupsShow({ backup, restoreInstructions }) {
    const verifyBackup = () => {
        router.post(route('backups.verify', backup.id));
    };

    const deleteBackup = () => {
        if (window.confirm(`Delete backup ${backup.filename}?`)) {
            router.delete(route('backups.destroy', backup.id));
        }
    };

    return (
        <AppLayout title="Backup Details">
            <Head title="Backup Details" />

            <BackLink href={route('backups.index')}>Back to Backups</BackLink>

            <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-lg font-semibold text-slate-900">{backup.filename}</h1>
                        <p className="mt-1 text-sm text-slate-600">{backup.backup_type_label}</p>
                    </div>
                    <StatusBadge
                        label={backup.backup_status_label}
                        color={statusColors[backup.backup_status] ?? 'slate'}
                    />
                </div>

                <dl className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Detail label="File Size" value={phpBytes(backup.file_size)} />
                    <Detail label="Storage Path" value={backup.file_path} />
                    <Detail label="Created By" value={backup.created_by ?? 'System'} />
                    <Detail
                        label="Started"
                        value={backup.started_at ? new Date(backup.started_at).toLocaleString('en-PH') : '—'}
                    />
                    <Detail
                        label="Completed"
                        value={backup.completed_at ? new Date(backup.completed_at).toLocaleString('en-PH') : '—'}
                    />
                    <Detail
                        label="Verification"
                        value={
                            backup.verification_passed === true
                                ? 'Passed'
                                : backup.verification_passed === false
                                  ? 'Failed'
                                  : 'Not verified'
                        }
                    />
                    <Detail label="Verification Message" value={backup.verification_message} />
                    <Detail label="Notes" value={backup.notes} />
                </dl>

                <div className="mt-6 flex flex-wrap gap-3">
                    {backup.can_download && (
                        <a
                            href={route('backups.download', backup.id)}
                            className="rounded-lg bg-sterling-gold px-4 py-2 text-sm font-semibold text-sterling-green-darker hover:bg-sterling-gold-light"
                        >
                            Download Backup
                        </a>
                    )}
                    <button
                        type="button"
                        onClick={verifyBackup}
                        className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Verify Integrity
                    </button>
                    <button
                        type="button"
                        onClick={deleteBackup}
                        className="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                    >
                        Delete Backup
                    </button>
                </div>
            </div>

            <div className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Manual restore instructions</h2>
                <ol className="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                    {restoreInstructions.map((step) => (
                        <li key={step}>{step}</li>
                    ))}
                </ol>
                <p className="mt-4 text-sm text-slate-600">
                    Need another backup? Return to{' '}
                    <Link href={route('backups.index')} className="text-sterling-green hover:underline">
                        Backup Management
                    </Link>
                    .
                </p>
            </div>
        </AppLayout>
    );
}
