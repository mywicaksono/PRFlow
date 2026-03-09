import { useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';

import { AppLayout } from '@/components/layout/AppLayout';
import { RequestFilters } from '@/features/requests/components/RequestFilters';
import { RequestPagination } from '@/features/requests/components/RequestPagination';
import { RequestsTable } from '@/features/requests/components/RequestsTable';
import { useRequests } from '@/features/requests/hooks/useRequests';
import type { ListRequestsParams } from '@/features/requests/types/requestTypes';

function parseRequestQueryParams(searchParams: URLSearchParams): ListRequestsParams {
  return {
    page: Number(searchParams.get('page') ?? '1'),
    status: (searchParams.get('status') as ListRequestsParams['status']) ?? '',
    search: searchParams.get('search') ?? '',
    sort_by: (searchParams.get('sort_by') as ListRequestsParams['sort_by']) ?? 'created_at',
    sort_direction: (searchParams.get('sort_direction') as ListRequestsParams['sort_direction']) ?? 'desc',
  };
}

export function RequestsPage() {
  const [searchParams, setSearchParams] = useSearchParams();

  const queryParams = useMemo(() => parseRequestQueryParams(searchParams), [searchParams]);
  const { data, isLoading, isError, error } = useRequests(queryParams);

  const applyFilters = (next: ListRequestsParams) => {
    // Filters are mirrored to URL so refresh/back-forward keeps table state.
    setSearchParams(
      Object.entries(next).reduce<Record<string, string>>((accumulator, [key, value]) => {
        if (value !== undefined && value !== '') {
          accumulator[key] = String(value);
        }

        return accumulator;
      }, {}),
    );
  };

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-semibold text-slate-800">Requests</h2>
          <Link to="#" className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700">
            + Create Request
          </Link>
        </div>

        <RequestFilters value={queryParams} onChange={applyFilters} />

        {isLoading ? <div className="rounded-lg border border-slate-200 bg-white p-6 text-slate-600">Loading requests...</div> : null}

        {isError ? (
          <div className="rounded-lg border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">
            Failed to load requests. {(error as Error).message}
          </div>
        ) : null}

        {!isLoading && !isError && data?.data.length === 0 ? (
          <div className="rounded-lg border border-slate-200 bg-white p-6 text-slate-600">No requests found for current filters.</div>
        ) : null}

        {!isLoading && !isError && data?.data.length ? <RequestsTable items={data.data} /> : null}

        {!isLoading && !isError && data ? (
          <RequestPagination
            currentPage={data.current_page}
            lastPage={data.last_page}
            onPageChange={(page) => applyFilters({ ...queryParams, page })}
          />
        ) : null}
      </div>
    </AppLayout>
  );
}
