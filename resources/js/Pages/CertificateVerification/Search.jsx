import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Search() {
    const { data, setData, post, processing, errors } = useForm({
        confirmation_number: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('certificate-verification.lookup'));
    };

    return (
        <GuestLayout unified>
            <Head title="Verify Certificate" />

            <h2 className="text-center font-serif text-xl font-bold text-sterling-green">
                Verify Certificate
            </h2>
            <p className="mt-2 text-center text-sm text-slate-500">
                Enter the confirmation number printed on the certificate.
            </p>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div>
                    <InputLabel htmlFor="confirmation_number" value="Confirmation Number" />

                    <TextInput
                        id="confirmation_number"
                        className="mt-1 block w-full font-mono text-sm uppercase"
                        value={data.confirmation_number}
                        onChange={(e) => setData('confirmation_number', e.target.value)}
                        placeholder="SICI-BOND-2026-8F4A72C1-V1"
                        required
                    />

                    <InputError className="mt-2" message={errors.confirmation_number} />
                </div>

                <PrimaryButton className="w-full justify-center" disabled={processing}>
                    Verify Certificate
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
