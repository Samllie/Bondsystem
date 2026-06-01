import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ principal, canUpdate }) {
    return (
        <AppLayout
            title={principal.company_name}
            actions={
                canUpdate && (
                    <Link href={route('principals.edit', principal.id)}>
                        <SecondaryButton>Edit</SecondaryButton>
                    </Link>
                )
            }
        >
            <Head title={principal.company_name} />
            <Card>
                <CardBody>
                    <dl className="grid gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt className="text-slate-500">Company</dt>
                            <dd className="font-medium">{principal.company_name}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Contact</dt>
                            <dd>{principal.contact_person}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Email</dt>
                            <dd>{principal.email}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Phone</dt>
                            <dd>{principal.phone_number}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-slate-500">Address</dt>
                            <dd>{principal.address}</dd>
                        </div>
                    </dl>
                </CardBody>
            </Card>
        </AppLayout>
    );
}
