import Card, { CardBody } from '@/Components/UI/Card';
import { SelectField } from '@/Components/UI/FormField';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function DashboardFilters({
    filters,
    statusOptions,
    bondTypeOptions,
    branchOptions = [],
    showBranchFilter = false,
}) {
    const [localFilters, setLocalFilters] = useState({
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        status: filters.status ?? '',
        bond_type_id: filters.bond_type_id ? String(filters.bond_type_id) : '',
        branch_id: filters.branch_id ? String(filters.branch_id) : '',
    });

    useEffect(() => {
        setLocalFilters({
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
            status: filters.status ?? '',
            bond_type_id: filters.bond_type_id ? String(filters.bond_type_id) : '',
            branch_id: filters.branch_id ? String(filters.branch_id) : '',
        });
    }, [filters.date_from, filters.date_to, filters.status, filters.bond_type_id, filters.branch_id]);

    const buildParams = () => ({
        date_from: localFilters.date_from || undefined,
        date_to: localFilters.date_to || undefined,
        status: localFilters.status || undefined,
        bond_type_id: localFilters.bond_type_id || undefined,
        branch_id: localFilters.branch_id || undefined,
        view: filters.view && filters.view !== 'overview' ? filters.view : undefined,
    });

    const applyFilters = () => {
        router.get(route('dashboard'), buildParams(), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const clearFilters = () => {
        router.get(route('dashboard'), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const filterColumnClass = showBranchFilter
        ? 'grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5'
        : 'grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-4';

    return (
        <Card className="no-print mb-6">
            <CardBody>
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 className="text-sm font-semibold text-sterling-green">Report Filters</h2>
                        <p className="mt-1 text-xs text-slate-500">
                            Filter dashboard statistics, then print the report with the applied filters.
                        </p>
                    </div>
                    <div className={filterColumnClass}>
                        <div>
                            <label htmlFor="dashboard-date-from" className="block text-xs font-medium text-slate-600">
                                From
                            </label>
                            <input
                                id="dashboard-date-from"
                                type="date"
                                value={localFilters.date_from}
                                onChange={(e) => setLocalFilters((prev) => ({ ...prev, date_from: e.target.value }))}
                                className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                            />
                        </div>
                        <div>
                            <label htmlFor="dashboard-date-to" className="block text-xs font-medium text-slate-600">
                                To
                            </label>
                            <input
                                id="dashboard-date-to"
                                type="date"
                                value={localFilters.date_to}
                                onChange={(e) => setLocalFilters((prev) => ({ ...prev, date_to: e.target.value }))}
                                className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                            />
                        </div>
                        {showBranchFilter && (
                            <SelectField
                                label="Branch"
                                value={localFilters.branch_id}
                                onChange={(e) => setLocalFilters((prev) => ({ ...prev, branch_id: e.target.value }))}
                                options={[{ value: '', label: 'All branches' }, ...branchOptions]}
                            />
                        )}
                        <SelectField
                            label="Status"
                            value={localFilters.status}
                            onChange={(e) => setLocalFilters((prev) => ({ ...prev, status: e.target.value }))}
                            options={[{ value: '', label: 'All statuses' }, ...statusOptions]}
                        />
                        <SelectField
                            label="Bond Type"
                            value={localFilters.bond_type_id}
                            onChange={(e) => setLocalFilters((prev) => ({ ...prev, bond_type_id: e.target.value }))}
                            options={[{ value: '', label: 'All bond types' }, ...bondTypeOptions]}
                        />
                    </div>
                    <div className="flex shrink-0 gap-2">
                        <button type="button" onClick={applyFilters} className="btn-primary">
                            Apply Filters
                        </button>
                        <button type="button" onClick={clearFilters} className="btn-secondary">
                            Clear
                        </button>
                    </div>
                </div>
            </CardBody>
        </Card>
    );
}
