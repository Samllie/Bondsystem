import { SelectField, TextAreaField, TextField } from '@/Components/UI/FormField';
import BackLink from '@/Components/UI/BackLink';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const backLabels = {
    'maintenance.bond-types': 'Bond Types',
    'maintenance.branches': 'Branches',
    'maintenance.ctcs': 'CTCs',
};

export default function MaintenanceForm({ record, routePrefix, label }) {
    const isEditing = !!record;
    const isBondTypeForm = routePrefix.includes('bond-types');
    const isBranchForm = routePrefix.includes('branches');

    const { data, setData, post, put, processing, errors } = useForm({
        name: record?.name ?? '',
        position: record?.position ?? '',
        commission_number: record?.commission_number ?? '',
        expiry_date: record?.expiry_date ?? '',
        code: record?.code ?? '',
        bond_serial: record?.bond_serial ?? '',
        branch_code: record?.branch_code ?? '',
        branch_city: record?.branch_city ?? '',
        notary_price: record?.notary_price ?? '',
        minimum_balance: record?.minimum_balance ?? '1000',
        description: record?.description ?? '',
        address: record?.address ?? '',
        contact: record?.contact ?? '',
        is_active: record?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route(`${routePrefix}.update`, record.id));
        } else {
            post(route(`${routePrefix}.store`));
        }
    };

    const showCode = record ? 'code' in record : routePrefix.includes('bond-types');
    const showDescription = record
        ? 'description' in record
        : routePrefix.includes('bond-types') || routePrefix.includes('certifications') || routePrefix.includes('ctcs');
    const showBranch = record ? 'address' in record : routePrefix.includes('branches');

    return (
        <AppLayout title={`${isEditing ? 'Edit' : 'New'} ${label}`}>
            <Head title={`${isEditing ? 'Edit' : 'New'} ${label}`} />

            <div className="mx-auto max-w-xl">
                <BackLink href={route(`${routePrefix}.index`)}>
                    Back to {backLabels[routePrefix] ?? label}
                </BackLink>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form onSubmit={submit} className="space-y-5">
                        <TextField
                            label={isBondTypeForm ? 'Bond Type' : 'Name'}
                            required
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                        />

                        {showCode && (
                            <TextField
                                label={isBondTypeForm ? 'Bond Number' : 'Code'}
                                required={isBondTypeForm}
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder={isBondTypeForm ? 'G(42)' : undefined}
                                error={errors.code}
                            />
                        )}

                        {showBranch && (
                            <TextField
                                label="Branch Code"
                                required
                                value={data.branch_code}
                                onChange={(e) => setData('branch_code', e.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3))}
                                maxLength={3}
                                placeholder="CEB"
                                error={errors.branch_code}
                            />
                        )}

                        {showCode && isBondTypeForm && (
                            <TextField
                                label="Bond Serial"
                                required
                                value={data.bond_serial}
                                onChange={(e) => setData('bond_serial', e.target.value.replace(/\D/g, '').slice(0, 7))}
                                inputMode="numeric"
                                maxLength={7}
                                placeholder="0000001"
                                error={errors.bond_serial}
                            />
                        )}

                        {showDescription && (
                            <TextAreaField
                                label={isBondTypeForm ? 'Bond Description' : 'Description'}
                                required={false}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                error={errors.description}
                            />
                        )}

                        {showBranch && (
                            <>
                                <TextField
                                    label="Branch City"
                                    value={data.branch_city}
                                    onChange={(e) => setData('branch_city', e.target.value)}
                                    placeholder="e.g. Cebu City"
                                    error={errors.branch_city}
                                />
                                <TextAreaField
                                    label="Address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    error={errors.address}
                                />
                                <TextField
                                    label="Contact"
                                    value={data.contact}
                                    onChange={(e) => setData('contact', e.target.value)}
                                    error={errors.contact}
                                />
                                <TextField
                                    label="Notary Price"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={data.notary_price}
                                    onChange={(e) => setData('notary_price', e.target.value)}
                                    error={errors.notary_price}
                                />
                                <TextField
                                    label="Minimum Branch Balance"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={data.minimum_balance}
                                    onChange={(e) => setData('minimum_balance', e.target.value)}
                                    error={errors.minimum_balance}
                                />
                                <p className="text-xs text-slate-500">
                                    Minimum fund required before a requester can submit a bond request.
                                </p>
                            </>
                        )}

                        <div className="flex items-center gap-3">
                            <input
                                id="is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-sterling-gold"
                            />
                            <label htmlFor="is_active" className="text-sm font-medium text-slate-700">Active</label>
                        </div>

                        <div className="flex justify-end gap-3 pt-2">
                            <Link href={route(`${routePrefix}.index`)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
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
