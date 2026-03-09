import type { RecentDashboardRequest } from '@/features/dashboard/types/dashboardTypes';
import { formatCurrency, formatDateTime } from '@/utils/formatters';

interface RecentRequestsListProps {
  items: RecentDashboardRequest[];
}

export function RecentRequestsList({ items }: RecentRequestsListProps) {
  return (
    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-50 text-left text-slate-600">
          <tr>
            <th className="px-4 py-3 font-medium">Request #</th>
            <th className="px-4 py-3 font-medium">Amount</th>
            <th className="px-4 py-3 font-medium">Status</th>
            <th className="px-4 py-3 font-medium">Submitted At</th>
          </tr>
        </thead>
        <tbody>
          {items.map((item) => (
            <tr key={item.id} className="border-t border-slate-200">
              <td className="px-4 py-3 font-medium text-slate-800">{item.request_number}</td>
              <td className="px-4 py-3 text-slate-700">{formatCurrency(item.amount)}</td>
              <td className="px-4 py-3 text-slate-700 uppercase">{item.status}</td>
              <td className="px-4 py-3 text-slate-600">{formatDateTime(item.submitted_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
