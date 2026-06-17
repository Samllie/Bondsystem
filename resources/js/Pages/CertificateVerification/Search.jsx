import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import BackLink from '@/Components/UI/BackLink';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Search() {
    const { auth } = usePage().props;
    const backHref = auth?.user ? route('dashboard') : route('login');
    const backLabel = auth?.user ? 'Back to Dashboard' : 'Back to Login';

    const { data, setData, post, processing, errors } = useForm({
        confirmation_number: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('certificate-verification.lookup'));
    };

    return (
        <GuestLayout unified>
            <Head title="Verify Confirmation" />

            <BackLink href={backHref} className="mb-0">
                {backLabel}
            </BackLink>

            <h2 className="text-center font-serif text-xl font-bold text-sterling-green">
                Verify Confirmation
            </h2>
            <p className="mt-2 text-center text-sm text-slate-500">
                Enter the full confirmation number or the 8-character code from the confirmation (e.g. 67F3CB62).
            </p>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div>
                    <InputLabel htmlFor="confirmation_number" value="Confirmation Number" />

                    <TextInput
                        id="confirmation_number"
                        className="mt-1 block w-full font-mono text-sm uppercase"
                        value={data.confirmation_number}
                        onChange={(e) => setData('confirmation_number', e.target.value)}
                        placeholder="SICI-BOND-2026-67F3CB62-V1 or 67F3CB62"
                        required
                    />

                    <InputError className="mt-2" message={errors.confirmation_number} />
                </div>

                <PrimaryButton className="w-full justify-center" disabled={processing}>
                    Verify Confirmation
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
