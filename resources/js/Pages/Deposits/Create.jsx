import AppLayout from '@/Layouts/AppLayout';
import BackLink from '@/Components/UI/BackLink';
import { Head, Link, useForm } from '@inertiajs/react';

const php = (v) => Number(v).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });

export default function DepositsCreate({ bankAccounts, userBalance }) {
    const { data, setData, post, processing, errors } = useForm({
        bank_account_id: '',
        amount: '',
        reference_number: '',
        receipt: null,
        deposit_date: new Date().toISOString().slice(0, 10),
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('payments.deposits.store'), { forceFormData: true });
    };

    return (
        <AppLayout title="Submit Deposit Request">
            <Head title="New Deposit" />

            <BackLink href={route('payments.deposits.index')}>Back to Deposits</BackLink>

            <div className="mx-auto max-w-2xl">
                {/* Balance card */}
                <div className="mb-6 rounded-xl border-2 border-sterling-gold/40 bg-sterling-gold-50 px-6 py-4">
                    <p className="text-sm font-medium text-sterling-green">Current Balance</p>
                    <p className="mt-1 text-3xl font-bold text-sterling-green-darker">{php(userBalance)}</p>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-800">New Deposit Request</h2>
                    <p className="mt-1 text-sm text-slate-500">Transfer funds to one of our bank accounts below, then submit this form with your proof of transfer.</p>

                    {/* Bank accounts reference */}
                    <div className="my-4 space-y-2 rounded-lg bg-slate-50 p-4">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Our Bank Accounts</p>
                        {bankAccounts.map((b) => (
                            <div key={b.id} className="flex items-start gap-3 text-sm">
                                <span className="mt-0.5 shrink-0 rounded bg-sterling-gold-50 px-2 py-0.5 text-xs font-medium text-sterling-green-dark">{b.bank_name}</span>
                                <div>
                                    <p className="font-mono font-medium text-slate-800">{b.account_number}</p>
                                    <p className="text-xs text-slate-500">{b.account_name} — {b.branch}</p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <form onSubmit={submit} encType="multipart/form-data" className="mt-6 space-y-5">
                        {/* Bank Account */}
                        <div>
                            <label className="block text-sm font-medium text-slate-700">Bank Account Transferred To <span className="text-red-500">*</span></label>
                            <select
                                value={data.bank_account_id}
                                onChange={(e) => setData('bank_account_id', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                            >
                                <option value="">Select bank account</option>
                                {bankAccounts.map((b) => (
                                    <option key={b.id} value={b.id}>{b.bank_name} — {b.account_number}</option>
                                ))}
                            </select>
                            {errors.bank_account_id && <p className="mt-1 text-xs text-red-500">{errors.bank_account_id}</p>}
                        </div>

                        {/* Amount */}
                        <div>
                            <label className="block text-sm font-medium text-slate-700">Amount (₱) <span className="text-red-500">*</span></label>
                            <input
                                type="number"
                                min="1"
                                step="0.01"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                placeholder="0.00"
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                            />
                            {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount}</p>}
                        </div>

                        {/* Reference Number */}
                        <div>
                            <label className="block text-sm font-medium text-slate-700">Reference Number <span className="text-red-500">*</span></label>
                            <input
                                type="text"
                                value={data.reference_number}
                                onChange={(e) => setData('reference_number', e.target.value)}
                                placeholder="Bank transfer reference / transaction ID"
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                            />
                            {errors.reference_number && <p className="mt-1 text-xs text-red-500">{errors.reference_number}</p>}
                        </div>

                        {/* Deposit Date */}
                        <div>
                            <label className="block text-sm font-medium text-slate-700">Date of Deposit <span className="text-red-500">*</span></label>
                            <input
                                type="date"
                                value={data.deposit_date}
                                onChange={(e) => setData('deposit_date', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sterling-gold focus:outline-none"
                            />
                            {errors.deposit_date && <p className="mt-1 text-xs text-red-500">{errors.deposit_date}</p>}
                        </div>

                        {/* Receipt Upload */}
                        <div>
                            <label className="block text-sm font-medium text-slate-700">Proof of Transfer / Receipt <span className="text-red-500">*</span></label>
                            <p className="text-xs text-slate-500">JPG, PNG, or PDF — max 5MB</p>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                onChange={(e) => setData('receipt', e.target.files[0])}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm file:mr-3 file:rounded file:border-0 file:bg-sterling-gold file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-sterling-green-darker"
                            />
                            {errors.receipt && <p className="mt-1 text-xs text-red-500">{errors.receipt}</p>}
                        </div>

                        <div className="flex justify-end gap-3 pt-2">
                            <Link href={route('payments.deposits.index')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-sterling-gold px-6 py-2 text-sm font-semibold text-sterling-green-darker hover:bg-sterling-gold-light disabled:opacity-60"
                            >
                                {processing ? 'Submitting…' : 'Submit Deposit Request'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
