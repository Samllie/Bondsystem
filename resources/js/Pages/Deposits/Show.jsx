import ConfirmModal from '@/Components/UI/ConfirmModal';
import StatusBadge from '@/Components/UI/StatusBadge';
import { useToast } from '@/Contexts/ToastContext';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
const statusColors = { pending: 'amber', approved: 'green', rejected: 'red' };
const statusLabels = { pending: 'Pending', approved: 'Approved', rejected: 'Rejected' };

export default function DepositsShow({ deposit, receiptUrl, canApprove, submitterBalance, branchName, transactionNumber }) {
    const { addToast } = useToast();
    const [approveModal, setApproveModal] = useState(false);
    const [rejectModal, setRejectModal] = useState(false);
    const approveForm = useForm({});
    const rejectForm = useForm({ remarks: '' });

    const approve = () => {
        approveForm.post(route('payments.deposits.approve', deposit.id), {
            preserveScroll: true,
            onSuccess: () => setApproveModal(false),
            onError: () => addToast('Unable to approve this deposit. It may already be reviewed.', 'error'),
        });
    };

    const reject = (e) => {
        e.preventDefault();
        rejectForm.post(route('payments.deposits.reject', deposit.id), {
            preserveScroll: true,
            onSuccess: () => setRejectModal(false),
            onError: () => addToast('Unable to reject this deposit.', 'error'),
        });
    };

    const isImageReceipt = receiptUrl?.match(/\.(jpg|jpeg|png)$/i);

    return (
        <AppLayout title="Deposit Details">
            <Head title="Deposit Details" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-start justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">Deposit #{deposit.id}</h2>
                            <p className="text-sm text-slate-500">Submitted by {deposit.user?.name}</p>
                        </div>
                        <StatusBadge
                            label={statusLabels[deposit.status] ?? deposit.status}
                            color={statusColors[deposit.status] ?? 'slate'}
                        />
                    </div>

                    <dl className="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <dt className="font-medium text-slate-500">Bank</dt>
                            <dd className="mt-1 text-slate-900">{deposit.bank_account?.bank_name}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-slate-500">Account Number</dt>
                            <dd className="mt-1 font-mono text-slate-900">{deposit.bank_account?.account_number}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-slate-500">Amount</dt>
                            <dd className="mt-1 text-2xl font-bold text-emerald-700">{php(deposit.amount)}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-slate-500">Reference Number</dt>
                            <dd className="mt-1 font-mono text-slate-900">{deposit.reference_number}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-slate-500">Date of Deposit</dt>
                            <dd className="mt-1 text-slate-900">{deposit.deposit_date}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-slate-500">{branchName ? `${branchName} Fund` : 'Branch Fund'}</dt>
                            <dd className="mt-1 text-lg font-bold text-sterling-green">{php(submitterBalance)}</dd>
                        </div>
                        {transactionNumber && (
                            <div>
                                <dt className="font-medium text-slate-500">Transaction Number</dt>
                                <dd className="mt-1 font-mono text-sm font-semibold text-sterling-green">{transactionNumber}</dd>
                            </div>
                        )}
                        {deposit.approved_by && (
                            <div>
                                <dt className="font-medium text-slate-500">Reviewed By</dt>
                                <dd className="mt-1 text-slate-900">{deposit.approver?.name}</dd>
                            </div>
                        )}
                        {deposit.remarks && (
                            <div className="col-span-2">
                                <dt className="font-medium text-slate-500">Remarks</dt>
                                <dd className="mt-1 text-slate-900">{deposit.remarks}</dd>
                            </div>
                        )}
                    </dl>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="mb-3 text-sm font-semibold text-slate-700">Proof of Transfer</h3>
                    {isImageReceipt ? (
                        <img src={receiptUrl} alt="Receipt" className="max-h-96 rounded-lg border border-slate-200" />
                    ) : (
                        <a href={receiptUrl} target="_blank" rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 rounded-lg border border-sterling-gold/40 px-4 py-2 text-sm text-sterling-green hover:bg-sterling-gold-50">
                            View Receipt PDF →
                        </a>
                    )}
                </div>

                {canApprove && (
                    <div className="flex gap-3">
                        <button
                            type="button"
                            onClick={() => setApproveModal(true)}
                            className="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                        >
                            Approve Deposit
                        </button>
                        <button
                            type="button"
                            onClick={() => setRejectModal(true)}
                            className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700"
                        >
                            Reject
                        </button>
                    </div>
                )}

                <Link href={route('payments.deposits.index')} className="block text-sm text-sterling-green hover:underline">
                    ← Back to Deposits
                </Link>
            </div>

            <ConfirmModal
                show={approveModal}
                onClose={() => setApproveModal(false)}
                onConfirm={approve}
                title="Approve Deposit"
                message={`Confirm approval of ${php(deposit.amount)}? The branch fund will be credited immediately.`}
                confirmLabel="Yes, Approve"
                processing={approveForm.processing}
            />

            {rejectModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold text-slate-900">Reject Deposit</h3>
                        <p className="mt-1 text-sm text-slate-500">Optionally provide a reason for rejection.</p>
                        <form onSubmit={reject} className="mt-4 space-y-4">
                            <textarea
                                value={rejectForm.data.remarks}
                                onChange={(e) => rejectForm.setData('remarks', e.target.value)}
                                rows={3}
                                placeholder="Reason for rejection (optional)"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sterling-gold focus:outline-none"
                            />
                            <div className="flex justify-end gap-3">
                                <button type="button" onClick={() => setRejectModal(false)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button type="submit" disabled={rejectForm.processing} className="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                                    Reject Deposit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
