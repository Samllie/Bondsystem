import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ obligee, canUpdate }) {
    return (
        <AppLayout
            title={obligee.company_name}
            actions={
                canUpdate && (
                    <Link href={route('obligees.edit', obligee.id)}>
                        <SecondaryButton>Edit</SecondaryButton>
                    </Link>
                )
            }
        >
            <Head title={obligee.company_name} />
            <Card>
                <CardBody>
                    <dl className="grid gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt className="text-slate-500">Company</dt>
                            <dd className="font-medium">{obligee.company_name}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Contact</dt>
                            <dd>{obligee.contact_person}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Email</dt>
                            <dd>{obligee.email}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Phone</dt>
                            <dd>{obligee.phone_number}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-slate-500">Address</dt>
                            <dd>{obligee.address}</dd>
                        </div>
                    </dl>
                </CardBody>
            </Card>
        </AppLayout>
    );
}
