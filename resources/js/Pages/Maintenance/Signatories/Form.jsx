import { FormField, TextField } from '@/Components/UI/FormField';
import BackLink from '@/Components/UI/BackLink';
import TinField from '@/Components/UI/TinField';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function SignatoryForm({ signatory }) {
    const isEditing = Boolean(signatory?.id);

    const { data, setData, post, processing, errors } = useForm({
        name: signatory?.name || '',
        position: signatory?.position || '',
        tin: signatory?.tin || '',
        signature: null,
        _method: isEditing ? 'PUT' : undefined,
    });

    const submit = (e) => {
        e.preventDefault();

        const url = isEditing
            ? route('maintenance.signatories.update', signatory.id)
            : route('maintenance.signatories.store');

        post(url, {
            forceFormData: true,
            preserveScroll: true,
            transform: (formData) => {
                const payload = { ...formData };

                if (isEditing) {
                    payload._method = 'PUT';
                }

                if (!payload.signature) {
                    delete payload.signature;
                }

                return payload;
            },
        });
    };

    return (
        <AppLayout title={`${isEditing ? 'Edit' : 'New'} Signatory`}>
            <Head title={`${isEditing ? 'Edit' : 'New'} Signatory`} />

            <div className="mx-auto max-w-xl">
                <BackLink href={route('maintenance.signatories.index')}>Back to Signatories</BackLink>

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
                            label="Position"
                            required
                            value={data.position}
                            onChange={(e) => setData('position', e.target.value)}
                            error={errors.position}
                        />
                        <TinField
                            label="TIN"
                            required
                            value={data.tin}
                            onChange={(value) => setData('tin', value)}
                            error={errors.tin}
                        />

                        <FormField label="Signature (PNG)" error={errors.signature} required={!isEditing}>
                            {isEditing && signatory?.signature_url && (
                                <div className="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <p className="mb-2 text-xs text-slate-500">Current signature</p>
                                    <img
                                        src={signatory.signature_url}
                                        alt={`${signatory.name} signature`}
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
                                href={route('maintenance.signatories.index')}
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
