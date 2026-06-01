import { FormField, TextField } from '@/Components/UI/FormField';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function NotaryForm({ notary }) {
    const isEditing = Boolean(notary?.id);

    const { data, setData, post, put, processing, errors } = useForm({
        name: notary?.name ?? '',
        commission_number: notary?.commission_number ?? '',
        tin: notary?.tin ?? '',
        signature: null,
    });

    const submit = (e) => {
        e.preventDefault();

        if (isEditing) {
            put(route('maintenance.notaries.update', notary.id), { forceFormData: true });
        } else {
            post(route('maintenance.notaries.store'), { forceFormData: true });
        }
    };

    return (
        <AppLayout title={`${isEditing ? 'Edit' : 'New'} Notary`}>
            <Head title={`${isEditing ? 'Edit' : 'New'} Notary`} />

            <div className="mx-auto max-w-xl">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form onSubmit={submit} encType="multipart/form-data" className="space-y-5">
                        <TextField
                            label="Name"
                            required
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                        />
                        <TextField
                            label="Commission Number"
                            required
                            value={data.commission_number}
                            onChange={(e) => setData('commission_number', e.target.value)}
                            error={errors.commission_number}
                        />
                        <TextField
                            label="TIN"
                            required
                            value={data.tin}
                            onChange={(e) => setData('tin', e.target.value)}
                            error={errors.tin}
                        />

                        <FormField
                            label="Seal / Signature (PNG)"
                            error={errors.signature}
                            required={!isEditing}
                        >
                            {isEditing && notary?.signature_url && (
                                <div className="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <p className="mb-2 text-xs text-slate-500">Current seal</p>
                                    <img
                                        src={notary.signature_url}
                                        alt={`${notary.name} seal`}
                                        className="max-h-24 object-contain"
                                    />
                                </div>
                            )}
                            <input
                                type="file"
                                accept="image/png"
                                onChange={(e) => setData('signature', e.target.files[0] ?? null)}
                                className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sterling-gold-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-sterling-green-darker hover:file:bg-sterling-gold/30"
                            />
                            <p className="mt-1 text-xs text-slate-500">PNG format only, max 2 MB.</p>
                        </FormField>

                        <div className="flex justify-end gap-3 pt-2">
                            <Link
                                href={route('maintenance.notaries.index')}
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
