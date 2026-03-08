import type { PendingApprovalItem } from '@/features/approvals/types/approvalTypes';
import { formatCurrency, formatDateTime } from '@/utils/formatters';

interface PendingApprovalsTableProps {
  items: PendingApprovalItem[];
  onApprove: (requestId: number) => void;
  onReject: (requestId: number) => void;
  isActionLoading: boolean;
}

export function PendingApprovalsTable({
  items,
  onApprove,
  onReject,
  isActionLoading,
}: PendingApprovalsTableProps) {
  return (
    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-50 text-left text-slate-600">
          <tr>
            <th className="px-4 py-3 font-medium">Request #</th>
            <th className="px-4 py-3 font-medium">Description</th>
            <th className="px-4 py-3 font-medium">Amount</th>
            <th className="px-4 py-3 font-medium">Level</th>
            <th className="px-4 py-3 font-medium">Submitted</th>
            <th className="px-4 py-3 font-medium">Action</th>
          </tr>
        </thead>
        <tbody>
          {items.map((approval) => (
            <tr key={approval.id} className="border-t border-slate-200">
              <td className="px-4 py-3 font-medium text-slate-800">{approval.request?.request_number ?? '-'}</td>
              <td className="px-4 py-3 text-slate-600">{approval.request?.description ?? '-'}</td>
              <td className="px-4 py-3 text-slate-700">{formatCurrency(approval.request?.amount ?? 0)}</td>
              <td className="px-4 py-3 text-slate-600">{approval.level}</td>
              <td className="px-4 py-3 text-slate-600">{formatDateTime(approval.request?.submitted_at)}</td>
              <td className="px-4 py-3">
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    disabled={isActionLoading}
                    onClick={() => onApprove(approval.request_id)}
                    className="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
                  >
                    Approve
                  </button>
                  <button
                    type="button"
                    disabled={isActionLoading}
                    onClick={() => onReject(approval.request_id)}
                    className="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
                  >
                    Reject
                  </button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
