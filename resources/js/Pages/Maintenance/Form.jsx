import { SelectField, TextAreaField, TextField } from '@/Components/UI/FormField';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function MaintenanceForm({ record, routePrefix, label }) {
    const isEditing = !!record;
    const isBondTypeForm = routePrefix.includes('bond-types');

    const { data, setData, post, put, processing, errors } = useForm({
        name: record?.name ?? '',
        position: record?.position ?? '',
        commission_number: record?.commission_number ?? '',
        expiry_date: record?.expiry_date ?? '',
        code: record?.code ?? '',
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
                                error={errors.code}
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
