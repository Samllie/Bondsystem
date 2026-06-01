import AuthLoadingOverlay from '@/Components/Auth/AuthLoadingOverlay';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout unified>
            <Head title="Log in" />

            {processing && <AuthLoadingOverlay message="Authenticating" />}

            <h2 className="text-center font-serif text-xl font-bold text-sterling-green">Sign In</h2>
            <p className="mt-1 text-center text-sm text-slate-500">
                Sign in to manage insurance bond requests
            </p>

            {status && (
                <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-6" aria-busy={processing}>
                <fieldset disabled={processing} className={processing ? 'pointer-events-none opacity-60' : ''}>
                    <div>
                        <InputLabel htmlFor="email" value="Email" />

                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            autoComplete="username"
                            isFocused={!processing}
                            onChange={(e) => setData('email', e.target.value)}
                        />

                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="password" value="Password" />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            autoComplete="current-password"
                            onChange={(e) => setData('password', e.target.value)}
                        />

                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="mt-4 flex items-center justify-between gap-4">
                        <label className="flex items-center">
                            <Checkbox
                                name="remember"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            />
                            <span className="ms-2 text-sm text-slate-600">Remember me</span>
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-sm text-sterling-green underline hover:text-sterling-gold-dark focus:outline-none focus:ring-2 focus:ring-sterling-gold focus:ring-offset-2"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <PrimaryButton className="mt-6 w-full justify-center" disabled={processing}>
                        {processing ? 'Signing in…' : 'Log in'}
                    </PrimaryButton>
                </fieldset>
            </form>
        </GuestLayout>
    );
}
