import { TextField } from '@/Components/UI/FormField';
import BackLink from '@/Components/UI/BackLink';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function BankAccountForm({ bankAccount }) {
    const isEditing = Boolean(bankAccount?.id);

    const { data, setData, post, put, processing, errors } = useForm({
        bank_name: bankAccount?.bank_name ?? '',
        account_number: bankAccount?.account_number ?? '',
        account_name: bankAccount?.account_name ?? '',
        branch: bankAccount?.branch ?? '',
        is_active: bankAccount?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();

        if (isEditing) {
            put(route('maintenance.bank-accounts.update', bankAccount.id));
        } else {
            post(route('maintenance.bank-accounts.store'));
        }
    };

    return (
        <AppLayout title={`${isEditing ? 'Edit' : 'New'} Bank Account`}>
            <Head title={`${isEditing ? 'Edit' : 'New'} Bank Account`} />

            <div className="mx-auto max-w-xl">
                <BackLink href={route('maintenance.bank-accounts.index')}>Back to Bank Accounts</BackLink>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-5">
                        <TextField
                            label="Bank Name"
                            required
                            value={data.bank_name}
                            onChange={(e) => setData('bank_name', e.target.value)}
                            placeholder="e.g. BDO Unibank"
                            error={errors.bank_name}
                        />
                        <TextField
                            label="Account Number"
                            required
                            value={data.account_number}
                            onChange={(e) => setData('account_number', e.target.value)}
                            error={errors.account_number}
                        />
                        <TextField
                            label="Account Name"
                            required
                            value={data.account_name}
                            onChange={(e) => setData('account_name', e.target.value)}
                            placeholder="e.g. Sterling Insurance Company Inc."
                            error={errors.account_name}
                        />
                        <TextField
                            label="Branch"
                            value={data.branch}
                            onChange={(e) => setData('branch', e.target.value)}
                            placeholder="e.g. Makati Main Branch"
                            error={errors.branch}
                        />

                        <div className="flex items-center gap-3">
                            <input
                                id="is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-sterling-gold"
                            />
                            <label htmlFor="is_active" className="text-sm font-medium text-slate-700">
                                Active (shown on deposit page)
                            </label>
                        </div>

                        <div className="flex justify-end gap-3 pt-2">
                            <Link
                                href={route('maintenance.bank-accounts.index')}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-sterling-gold px-6 py-2 text-sm font-semibold text-sterling-green-darker hover:bg-sterling-gold-light disabled:opacity-60"
                            >
                                {processing ? 'Saving…' : (isEditing ? 'Update' : 'Create')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
