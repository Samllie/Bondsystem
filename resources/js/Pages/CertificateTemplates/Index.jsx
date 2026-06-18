import PrimaryButton from '@/Components/PrimaryButton';
import Card, { CardBody } from '@/Components/UI/Card';
import FileDownloadLink from '@/Components/UI/FileDownloadLink';
import { SelectField, TextField } from '@/Components/UI/FormField';
import Pagination from '@/Components/UI/Pagination';
import StatusBadge from '@/Components/UI/StatusBadge';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

function formatFileSize(bytes) {
    if (!bytes) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDate(isoString) {
    if (!isoString) {
        return '—';
    }

    return new Date(isoString).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function typeLabel(type) {
    return type === 'car' ? 'CAR' : 'Bond';
}

function SectionHeader({ title, description }) {
    return (
        <tr className="bg-slate-100">
            <td colSpan={8} className="px-4 py-2">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-600">{title}</p>
                {description && <p className="mt-0.5 text-xs font-normal normal-case text-slate-500">{description}</p>}
            </td>
        </tr>
    );
}

function TemplateRow({ template, canManage, onActivate, onActivateFallback, onArchive, highlight = false }) {
    const downloadHref =
        template.source === 'fallback'
            ? route('certificate-templates.download-fallback', template.template_type)
            : route('certificate-templates.download', template.id);

    return (
        <tr className={highlight ? 'bg-sterling-green-50/70 hover:bg-sterling-green-50' : 'hover:bg-slate-50'}>
            <td className="px-4 py-3 font-medium text-slate-900">{typeLabel(template.template_type)}</td>
            <td className="px-4 py-3 text-slate-800">
                {template.template_name}
                {template.source === 'fallback' && (
                    <span className="ml-2 text-xs font-normal text-slate-500">(resources/templates/)</span>
                )}
            </td>
            <td className="px-4 py-3 text-slate-600">{template.version ? `v${template.version}` : '—'}</td>
            <td className="px-4 py-3 text-slate-600">
                <div>{template.original_filename}</div>
                <div className="text-xs text-slate-400">{formatFileSize(template.file_size)}</div>
            </td>
            <td className="px-4 py-3 text-slate-600">{template.uploaded_by ?? '—'}</td>
            <td className="px-4 py-3 text-slate-600">{formatDate(template.created_at)}</td>
            <td className="px-4 py-3">
                <div className="flex flex-wrap gap-1.5">
                    {template.is_in_use && <StatusBadge label="In Use" color="green" />}
                    {template.is_previous && <StatusBadge label="Previous" color="amber" />}
                    {template.archived_at && <StatusBadge label="Archived" color="slate" />}
                    {!template.is_in_use &&
                        !template.is_previous &&
                        !template.archived_at &&
                        !template.is_active &&
                        template.source === 'uploaded' && <StatusBadge label="Inactive" color="amber" />}
                </div>
            </td>
            <td className="px-4 py-3">
                <div className="flex flex-wrap justify-end gap-2">
                    <FileDownloadLink
                        href={downloadHref}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Download
                    </FileDownloadLink>
                    {canManage && template.source === 'fallback' && template.is_previous && (
                        <button
                            type="button"
                            onClick={() => onActivateFallback(template.template_type)}
                            className="rounded-lg bg-sterling-gold px-3 py-1.5 text-xs font-semibold text-sterling-green-darker hover:bg-sterling-gold-light"
                        >
                            Reactivate
                        </button>
                    )}
                    {canManage && template.id && !template.archived_at && !template.is_active && (
                        <button
                            type="button"
                            onClick={() => onActivate(template.id)}
                            className="rounded-lg bg-sterling-gold px-3 py-1.5 text-xs font-semibold text-sterling-green-darker hover:bg-sterling-gold-light"
                        >
                            Reactivate
                        </button>
                    )}
                    {canManage && template.id && !template.archived_at && (
                        <button
                            type="button"
                            onClick={() => onArchive(template.id)}
                            className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                        >
                            Archive
                        </button>
                    )}
                </div>
            </td>
        </tr>
    );
}

export default function CertificateTemplatesIndex({
    inUseTemplates,
    previousTemplates,
    archivedTemplates,
    canManage,
    templateTypeOptions,
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        template_name: '',
        template_type: 'bond',
        template: null,
    });

    const submitUpload = (event) => {
        event.preventDefault();

        post(route('certificate-templates.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset('template_name', 'template_type', 'template');
            },
        });
    };

    const activateTemplate = (id) => {
        router.patch(route('certificate-templates.activate', id), {}, { preserveScroll: true });
    };

    const activateFallbackTemplate = (type) => {
        router.patch(route('certificate-templates.activate-fallback', type), {}, { preserveScroll: true });
    };

    const archiveTemplate = (id) => {
        router.patch(route('certificate-templates.archive', id), {}, { preserveScroll: true });
    };

    const hasAnyRows =
        (inUseTemplates?.length ?? 0) > 0 ||
        (previousTemplates?.length ?? 0) > 0 ||
        (archivedTemplates?.data?.length ?? 0) > 0;

    return (
        <AppLayout title="Confirmation Templates">
            <Head title="Confirmation Templates" />

            {canManage && (
                <Card className="mb-6 max-w-3xl">
                    <CardBody>
                        <h2 className="text-lg font-semibold text-sterling-green">Upload Template</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Upload a DOCX template (max 10 MB). New uploads are inactive until activated. Only one Bond
                            and one CAR template can be active at a time.
                        </p>

                        <form onSubmit={submitUpload} encType="multipart/form-data" className="mt-5 space-y-4">
                            <TextField
                                label="Template Name"
                                value={data.template_name}
                                onChange={(e) => setData('template_name', e.target.value)}
                                error={errors.template_name}
                                required
                            />

                            <SelectField
                                label="Template Type"
                                value={data.template_type}
                                onChange={(e) => setData('template_type', e.target.value)}
                                options={templateTypeOptions}
                                error={errors.template_type}
                                required
                            />

                            <div>
                                <label htmlFor="template" className="block text-sm font-medium text-slate-700">
                                    DOCX File
                                </label>
                                <input
                                    id="template"
                                    type="file"
                                    accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                    onChange={(e) => setData('template', e.target.files[0] ?? null)}
                                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm file:mr-3 file:rounded file:border-0 file:bg-sterling-gold file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-sterling-green-darker"
                                />
                                {errors.template && <p className="mt-1 text-sm text-red-600">{errors.template}</p>}
                            </div>

                            <PrimaryButton disabled={processing}>
                                {processing ? 'Uploading…' : 'Upload Template'}
                            </PrimaryButton>
                        </form>
                    </CardBody>
                </Card>
            )}

            <Card>
                <CardBody className="overflow-x-auto p-0">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Type</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Name</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Version</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Original File</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Uploaded By</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Upload Date</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-slate-500">Status</th>
                                <th className="px-4 py-3 text-right text-xs font-medium text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {inUseTemplates?.length > 0 && (
                                <>
                                    <SectionHeader
                                        title="Currently in use for confirmation generation"
                                        description="One Bond template and one CAR template are used when generating confirmations."
                                    />
                                    {inUseTemplates.map((template) => (
                                        <TemplateRow
                                            key={`in-use-${template.template_type}`}
                                            template={template}
                                            canManage={canManage}
                                            onActivate={activateTemplate}
                                            onActivateFallback={activateFallbackTemplate}
                                            onArchive={archiveTemplate}
                                            highlight
                                        />
                                    ))}
                                </>
                            )}

                            {previousTemplates?.length > 0 && (
                                <>
                                    <SectionHeader
                                        title="Previous templates"
                                        description="Inactive templates and the built-in fallback available for reactivation. Activating one moves the current template of the same type here."
                                    />
                                    {previousTemplates.map((template) => (
                                        <TemplateRow
                                            key={template.id ? `previous-${template.id}` : `previous-fallback-${template.template_type}`}
                                            template={template}
                                            canManage={canManage}
                                            onActivate={activateTemplate}
                                            onActivateFallback={activateFallbackTemplate}
                                            onArchive={archiveTemplate}
                                        />
                                    ))}
                                </>
                            )}

                            {archivedTemplates?.data?.length > 0 && (
                                <>
                                    <SectionHeader
                                        title="Archived templates"
                                        description="Archived templates are kept for history and download only."
                                    />
                                    {archivedTemplates.data.map((template) => (
                                        <TemplateRow
                                            key={`archived-${template.id}`}
                                            template={template}
                                            canManage={canManage}
                                            onActivate={activateTemplate}
                                            onActivateFallback={activateFallbackTemplate}
                                            onArchive={archiveTemplate}
                                        />
                                    ))}
                                </>
                            )}
                        </tbody>
                    </table>
                    {!hasAnyRows && (
                        <p className="px-6 py-8 text-center text-sm text-slate-500">
                            No confirmation templates uploaded yet. Fallback templates in resources/templates/ will be
                            used.
                        </p>
                    )}
                </CardBody>
            </Card>

            {archivedTemplates?.links?.length > 3 && (
                <div className="mt-6">
                    <Pagination links={archivedTemplates.links} meta={archivedTemplates.meta} />
                </div>
            )}
        </AppLayout>
    );
}
