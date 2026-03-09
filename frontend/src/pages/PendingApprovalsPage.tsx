import { useState } from 'react';

import { AppLayout } from '@/components/layout/AppLayout';
import { PendingApprovalsTable } from '@/features/approvals/components/PendingApprovalsTable';
import { useApprovalActions } from '@/features/approvals/hooks/useApprovalActions';
import { usePendingApprovals } from '@/features/approvals/hooks/usePendingApprovals';
import { RequestPagination } from '@/features/requests/components/RequestPagination';

function extractErrorMessage(error: unknown): string {
  if (!error || typeof error !== 'object') {
    return 'Action failed.';
  }

  const maybeAxiosError = error as { response?: { data?: { message?: string } } };
  return maybeAxiosError.response?.data?.message ?? 'Action failed.';
}

export function PendingApprovalsPage() {
  const [page, setPage] = useState(1);
  const [feedback, setFeedback] = useState<string | null>(null);

  const { data, isLoading, isError, error } = usePendingApprovals(page);
  const { approveMutation, rejectMutation } = useApprovalActions();

  const handleApprove = async (requestId: number) => {
    setFeedback(null);

    try {
      await approveMutation.mutateAsync(requestId);
      setFeedback('Request approved successfully.');
    } catch (actionError) {
      setFeedback(extractErrorMessage(actionError));
    }
  };

  const handleReject = async (requestId: number) => {
    const reason = window.prompt('Reject reason (required):', '');

    if (!reason || !reason.trim()) {
      return;
    }

    setFeedback(null);

    try {
      await rejectMutation.mutateAsync({ requestId, reason: reason.trim() });
      setFeedback('Request rejected successfully.');
    } catch (actionError) {
      setFeedback(extractErrorMessage(actionError));
    }
  };

  return (
    <AppLayout>
      <div className="space-y-4">
        <h2 className="text-xl font-semibold text-slate-800">Pending Approvals</h2>

        {feedback ? <div className="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-700">{feedback}</div> : null}

        {isLoading ? (
          <div className="rounded-lg border border-slate-200 bg-white p-6 text-slate-600">Loading pending approvals...</div>
        ) : null}

        {isError ? (
          <div className="rounded-lg border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">
            Failed to load pending approvals. {(error as Error).message}
          </div>
        ) : null}

        {!isLoading && !isError && data?.data.length === 0 ? (
          <div className="rounded-lg border border-slate-200 bg-white p-6 text-slate-600">No pending approvals right now.</div>
        ) : null}

        {!isLoading && !isError && data?.data.length ? (
          <PendingApprovalsTable
            items={data.data}
            onApprove={handleApprove}
            onReject={handleReject}
            isActionLoading={approveMutation.isPending || rejectMutation.isPending}
          />
        ) : null}

        {!isLoading && !isError && data ? (
          <RequestPagination currentPage={data.current_page} lastPage={data.last_page} onPageChange={setPage} />
        ) : null}
      </div>
    </AppLayout>
  );
}
