import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card, { CardBody, CardHeader } from '@/Components/UI/Card';
import ConfirmModal from '@/Components/UI/ConfirmModal';
import StatusBadge from '@/Components/UI/StatusBadge';
import AppLayout from '@/Layouts/AppLayout';
import { formatBookNoDisplay } from '@/lib/romanNumerals';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ bondRequest, canUpdate, canDelete, canApprove, canNotarize }) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const status = bondRequest.status?.value || bondRequest.status;

    const addresses = [bondRequest.address_1, bondRequest.address_2, bondRequest.address_3].filter(Boolean);

    return (
        <AppLayout
            title={`Bond ${bondRequest.bond_number}`}
            actions={
                <div className="flex flex-wrap gap-2">
                    {canUpdate && (
                        <Link href={route('bond-requests.edit', bondRequest.id)}>
                            <SecondaryButton>Edit</SecondaryButton>
                        </Link>
                    )}
                    {canApprove && (
                        <>
                            <PrimaryButton onClick={() => router.post(route('bond-requests.approve', bondRequest.id))}>
                                Approve
                            </PrimaryButton>
                            <SecondaryButton onClick={() => router.post(route('bond-requests.reject', bondRequest.id))}>
                                Reject
                            </SecondaryButton>
                        </>
                    )}
                    {canNotarize && (
                        <PrimaryButton onClick={() => router.post(route('bond-requests.notarize', bondRequest.id))}>
                            Mark Notarized
                        </PrimaryButton>
                    )}
                    {canDelete && (
                        <SecondaryButton onClick={() => setDeleteOpen(true)} className="!text-red-600">
                            Delete
                        </SecondaryButton>
                    )}
                </div>
            }
        >
            <Head title={bondRequest.bond_number} />

            <Card>
                <CardHeader
                    title="Bond Details"
                    action={<StatusBadge label={bondRequest.status_label || status} color={bondRequest.status_color || 'gray'} />}
                />
                <CardBody>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Detail label="Bond Number" value={bondRequest.bond_number} />
                        <Detail label="Bond Type" value={bondRequest.bond_type_label} />
                        <Detail label="Principal" value={bondRequest.principal?.company_name} />
                        <Detail label="Obligee" value={bondRequest.obligee?.company_name} />
                        <Detail
                            label="Address"
                            value={addresses.length ? addresses.join(', ') : '—'}
                            className="sm:col-span-2"
                            capitalize={false}
                        />
                        <Detail label="Request Date" value={bondRequest.request_date} />
                        <Detail label="Expiry Date" value={bondRequest.expiry_date} />
                        <Detail
                            label="Amount"
                            value={Number(bondRequest.amount).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}
                        />
                        <Detail label="Amount in words" value={bondRequest.amount_in_words || '—'} className="sm:col-span-2" capitalize={false} />
                        <Detail label="Project name" value={bondRequest.project_name || '—'} className="sm:col-span-2" />
                        <Detail label="Signatory" value={bondRequest.signatory?.name} />
                        <Detail label="Position" value={bondRequest.signatory_position || bondRequest.signatory?.position} />
                        <Detail label="Notary" value={bondRequest.notary?.name} />
                        <Detail label="Doc No." value={bondRequest.doc_no || '—'} />
                        <Detail label="Page No." value={bondRequest.page_no || '—'} />
                        <Detail label="Book No." value={formatBookNoDisplay(bondRequest.book_no) || '—'} />
                        <Detail label="Series year" value={bondRequest.series_year || '—'} />
                        <Detail label="Created By" value={bondRequest.creator?.name} />
                    </dl>
                </CardBody>
            </Card>

            <ConfirmModal
                show={deleteOpen}
                onClose={() => setDeleteOpen(false)}
                onConfirm={() => router.delete(route('bond-requests.destroy', bondRequest.id))}
                title="Delete Bond Request"
                message="This action cannot be undone."
                confirmLabel="Delete"
                danger
            />
        </AppLayout>
    );
}

function Detail({ label, value, className = '', capitalize = true }) {
    return (
        <div className={className}>
            <dt className="text-xs font-medium uppercase text-slate-500">{label}</dt>
            <dd className={`mt-1 text-sm text-slate-900 ${capitalize ? 'capitalize' : ''}`}>{value}</dd>
        </div>
    );
}
