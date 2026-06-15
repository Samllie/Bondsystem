import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { FormField, TextField } from '@/Components/UI/FormField';
import TinField from '@/Components/UI/TinField';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateAttorneyProfileForm({
    mustVerifyEmail,
    status,
    signatory,
    notary,
    className = '',
}) {
    const user = usePage().props.auth.user;

    const { data, setData, post, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
        signatory_position: signatory?.position ?? '',
        signatory_tin: signatory?.tin ?? '',
        signatory_signature: null,
        notary_commission_number: notary?.commission_number ?? '',
        notary_tin: notary?.tin ?? '',
        notary_signature: null,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('profile.update'), {
            forceFormData: true,
            _method: 'patch',
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Attorney Profile
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Update your account details, signatory position, signature, and notary seal.
                </p>
            </header>

            <form onSubmit={submit} encType="multipart/form-data" className="mt-6 space-y-8">
                <div className="space-y-6">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Account
                    </h3>

                    <div>
                        <InputLabel htmlFor="name" value="Name" />

                        <TextInput
                            id="name"
                            className="mt-1 block w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoComplete="name"
                        />

                        <InputError className="mt-2" message={errors.name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="email" value="Email" />

                        <TextInput
                            id="email"
                            type="email"
                            className="mt-1 block w-full"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="username"
                        />

                        <InputError className="mt-2" message={errors.email} />
                    </div>

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <div>
                            <p className="mt-2 text-sm text-gray-800">
                                Your email address is unverified.
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="rounded-md text-sm text-sterling-green underline hover:text-sterling-gold-dark focus:outline-none focus:ring-2 focus:ring-sterling-gold focus:ring-offset-2"
                                >
                                    Click here to re-send the verification email.
                                </Link>
                            </p>

                            {status === 'verification-link-sent' && (
                                <div className="mt-2 text-sm font-medium text-green-600">
                                    A new verification link has been sent to your email address.
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <div className="space-y-6 border-t border-slate-200 pt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Signatory Details
                    </h3>

                    <TextField
                        label="Position"
                        required
                        value={data.signatory_position}
                        onChange={(e) => setData('signatory_position', e.target.value)}
                        error={errors.signatory_position}
                    />

                    <TextField
                        label="TIN"
                        required
                        value={data.signatory_tin}
                        onChange={(e) => setData('signatory_tin', e.target.value)}
                        error={errors.signatory_tin}
                    />

                    <FormField label="Signature (PNG)" error={errors.signatory_signature}>
                        {signatory?.signature_url && (
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
                            onChange={(e) => setData('signatory_signature', e.target.files[0] ?? null)}
                            className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sterling-gold-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-sterling-green-darker hover:file:bg-sterling-gold/30"
                        />
                        <p className="mt-1 text-xs text-slate-500">PNG format only, max 2 MB.</p>
                    </FormField>
                </div>

                <div className="space-y-6 border-t border-slate-200 pt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Notary Details
                    </h3>

                    <TextField
                        label="Commission Number"
                        required
                        value={data.notary_commission_number}
                        onChange={(e) => setData('notary_commission_number', e.target.value)}
                        error={errors.notary_commission_number}
                    />

                    <TinField
                        label="TIN"
                        required
                        value={data.notary_tin}
                        onChange={(value) => setData('notary_tin', value)}
                        error={errors.notary_tin}
                    />

                    <FormField label="Seal / Signature (PNG)" error={errors.notary_signature}>
                        {notary?.signature_url && (
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
                            onChange={(e) => setData('notary_signature', e.target.files[0] ?? null)}
                            className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sterling-gold-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-sterling-green-darker hover:file:bg-sterling-gold/30"
                        />
                        <p className="mt-1 text-xs text-slate-500">PNG format only, max 10 MB.</p>
                    </FormField>
                </div>

                <div className="flex items-center gap-4 border-t border-slate-200 pt-6">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
