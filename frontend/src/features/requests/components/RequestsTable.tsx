import type { RequestItem } from '@/features/requests/types/requestTypes';
import { formatCurrency, formatDateTime } from '@/utils/formatters';

interface RequestsTableProps {
  items: RequestItem[];
}

export function RequestsTable({ items }: RequestsTableProps) {
  return (
    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-50 text-left text-slate-600">
          <tr>
            <th className="px-4 py-3 font-medium">Request #</th>
            <th className="px-4 py-3 font-medium">Description</th>
            <th className="px-4 py-3 font-medium">Amount</th>
            <th className="px-4 py-3 font-medium">Status</th>
            <th className="px-4 py-3 font-medium">Created</th>
          </tr>
        </thead>
        <tbody>
          {items.map((request) => (
            <tr key={request.id} className="border-t border-slate-200">
              <td className="px-4 py-3 font-medium text-slate-800">{request.request_number}</td>
              <td className="px-4 py-3 text-slate-600">{request.description}</td>
              <td className="px-4 py-3 text-slate-700">{formatCurrency(request.amount)}</td>
              <td className="px-4 py-3">
                <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium uppercase text-slate-700">
                  {request.status}
                </span>
              </td>
              <td className="px-4 py-3 text-slate-600">{formatDateTime(request.created_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
