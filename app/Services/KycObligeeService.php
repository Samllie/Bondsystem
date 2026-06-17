<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class KycObligeeService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(?string $term = null, int $limit = 20): array
    {
        $query = $this->baseQuery();

        if ($term !== null && $term !== '') {
            $nameColumn = config('kyc.columns.company_name');
            $query->where($nameColumn, 'like', '%'.$term.'%');
        }

        return $query
            ->orderBy(config('kyc.columns.company_name'))
            ->limit($limit)
            ->get()
            ->map(fn (stdClass $row) => $this->mapClient($row))
            ->all();
    }

    public function paginate(?string $term = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if ($term !== null && $term !== '') {
            $nameColumn = config('kyc.columns.company_name');
            $contactColumn = config('kyc.columns.contact_person');
            $emailColumn = config('kyc.columns.email');

            $query->where(function (Builder $inner) use ($term, $nameColumn, $contactColumn, $emailColumn) {
                $inner->where($nameColumn, 'like', '%'.$term.'%');

                if ($contactColumn) {
                    $inner->orWhere($contactColumn, 'like', '%'.$term.'%');
                }

                if ($emailColumn) {
                    $inner->orWhere($emailColumn, 'like', '%'.$term.'%');
                }
            });
        }

        return $query
            ->orderBy(config('kyc.columns.company_name'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (stdClass $row) => $this->mapClient($row));
    }

    public function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $row = $this->baseQuery()
            ->where(config('kyc.columns.id'), $id)
            ->first();

        return $row ? $this->mapClient($row) : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function findMany(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return $this->baseQuery()
            ->whereIn(config('kyc.columns.id'), $ids)
            ->get()
            ->mapWithKeys(fn (stdClass $row) => [
                (int) $this->value($row, 'id') => $this->mapClient($row),
            ]);
    }

    private function baseQuery(): Builder
    {
        return DB::connection(config('kyc.connection'))
            ->table(config('kyc.table'))
            ->where(config('kyc.columns.client_type'), config('kyc.obligee_type'));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapClient(stdClass $row): array
    {
        $companyName = $this->value($row, 'company_name')
            ?? $this->value($row, 'client_name');

        return [
            'id' => (int) $this->value($row, 'id'),
            'company_name' => $companyName,
            'label' => $companyName,
            'address' => $this->value($row, 'address'),
            'business_address' => $this->value($row, 'business_address'),
            'business_ctm' => $this->value($row, 'business_ctm'),
            'business_province' => $this->value($row, 'business_province'),
            'contact_person' => $this->value($row, 'contact_person'),
            'email' => $this->value($row, 'email'),
            'phone_number' => $this->value($row, 'phone_number'),
        ];
    }

    private function value(stdClass $row, string $key): mixed
    {
        $column = config("kyc.columns.{$key}");

        return $row->{$column} ?? null;
    }
}
