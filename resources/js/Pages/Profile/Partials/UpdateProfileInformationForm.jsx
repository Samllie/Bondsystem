import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import EditableCombobox from '@/Components/UI/EditableCombobox';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;
    const branchOptions = usePage().props.branchOptions ?? [];

    const branchComboboxOptions = useMemo(
        () =>
            branchOptions.map((option) => ({
                id: option.value,
                label: option.label,
                city: option.city,
                branch_code: option.branch_code,
            })),
        [branchOptions],
    );

    const initialBranchLabel = useMemo(() => {
        const selected = branchOptions.find(
            (option) => String(option.value) === String(user.branch_id),
        );

        return selected?.label ?? '';
    }, [branchOptions, user.branch_id]);

    const initialBranchCode = useMemo(() => {
        if (user.branch_code) {
            return user.branch_code;
        }

        const selected = branchOptions.find(
            (option) => String(option.value) === String(user.branch_id),
        );

        return selected?.branch_code ?? '';
    }, [branchOptions, user.branch_code, user.branch_id]);

    const [branchLabel, setBranchLabel] = useState(initialBranchLabel);

    const initialBranchOption = useMemo(() => {
        if (!user.branch_id) {
            return null;
        }

        const selected = branchComboboxOptions.find(
            (option) => String(option.id) === String(user.branch_id),
        );

        return selected ?? null;
    }, [branchComboboxOptions, user.branch_id]);

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            branch_id: user.branch_id ?? '',
            branch_code: initialBranchCode,
            branch_city: user.branch_city ?? '',
        });

    const handleBranchSelect = (option) => {
        setData((current) => ({
            ...current,
            branch_id: option.id,
            branch_code: option.branch_code ?? '',
            branch_city: option.city ?? '',
        }));
        setBranchLabel(option.label);
    };

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Profile Information
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Update your account's profile information and email address.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Name" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
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

                <EditableCombobox
                    label="Branch"
                    value={data.branch_id}
                    textValue={branchLabel}
                    onChange={(id) => setData('branch_id', id)}
                    onTextChange={setBranchLabel}
                    onOptionSelect={handleBranchSelect}
                    localOptions={branchComboboxOptions}
                    initialOption={initialBranchOption}
                    placeholder="Type or select a branch…"
                    error={errors.branch_id}
                />

                <div>
                    <InputLabel htmlFor="branch_code" value="Branch Code" />

                    <TextInput
                        id="branch_code"
                        className="mt-1 block w-full uppercase"
                        value={data.branch_code}
                        onChange={(e) =>
                            setData(
                                'branch_code',
                                e.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3),
                            )
                        }
                        maxLength={3}
                        placeholder="MKT"
                    />

                    <InputError className="mt-2" message={errors.branch_code} />
                </div>

                <div>
                    <InputLabel htmlFor="branch_city" value="Branch City" />

                    <TextInput
                        id="branch_city"
                        className="mt-1 block w-full"
                        value={data.branch_city}
                        onChange={(e) => setData('branch_city', e.target.value)}
                    />

                    <InputError className="mt-2" message={errors.branch_city} />
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
                                A new verification link has been sent to your
                                email address.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
