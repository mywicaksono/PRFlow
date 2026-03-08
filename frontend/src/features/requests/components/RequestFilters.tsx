import type { ListRequestsParams, RequestStatus } from '@/features/requests/types/requestTypes';

interface RequestFiltersProps {
  value: ListRequestsParams;
  onChange: (next: ListRequestsParams) => void;
}

export function RequestFilters({ value, onChange }: RequestFiltersProps) {
  const handleStatusChange = (nextStatus: string) => {
    onChange({
      ...value,
      status: (nextStatus as RequestStatus | '') || '',
      page: 1,
    });
  };

  return (
    <div className="grid gap-3 md:grid-cols-4">
      <input
        type="text"
        value={value.search ?? ''}
        onChange={(event) =>
          onChange({
            ...value,
            search: event.target.value,
            page: 1,
          })
        }
        placeholder="Search request number / description"
        className="rounded-md border border-slate-300 px-3 py-2 text-sm"
      />

      <select
        value={value.status ?? ''}
        onChange={(event) => handleStatusChange(event.target.value)}
        className="rounded-md border border-slate-300 px-3 py-2 text-sm"
      >
        <option value="">All statuses</option>
        <option value="draft">Draft</option>
        <option value="submitted">Submitted</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>

      <select
        value={value.sort_by ?? 'created_at'}
        onChange={(event) =>
          onChange({
            ...value,
            sort_by: event.target.value as ListRequestsParams['sort_by'],
            page: 1,
          })
        }
        className="rounded-md border border-slate-300 px-3 py-2 text-sm"
      >
        <option value="created_at">Sort by created date</option>
        <option value="amount">Sort by amount</option>
        <option value="status">Sort by status</option>
      </select>

      <select
        value={value.sort_direction ?? 'desc'}
        onChange={(event) =>
          onChange({
            ...value,
            sort_direction: event.target.value as ListRequestsParams['sort_direction'],
            page: 1,
          })
        }
        className="rounded-md border border-slate-300 px-3 py-2 text-sm"
      >
        <option value="desc">Desc</option>
        <option value="asc">Asc</option>
      </select>
    </div>
  );
}
