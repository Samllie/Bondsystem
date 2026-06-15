import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdateAttorneyProfileForm from './Partials/UpdateAttorneyProfileForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
    isAttorney = false,
    signatory = null,
    notary = null,
}) {
    return (
        <AppLayout title="Profile">
            <Head title="Profile" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    {isAttorney ? (
                        <UpdateAttorneyProfileForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            signatory={signatory}
                            notary={notary}
                            className="max-w-xl"
                        />
                    ) : (
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    )}
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <UpdatePasswordForm className="max-w-xl" />
                </div>

                {!isAttorney && (
                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
