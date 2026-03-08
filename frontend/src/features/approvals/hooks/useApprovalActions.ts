import { useMutation, useQueryClient } from '@tanstack/react-query';

import { approveRequest, rejectRequest } from '@/features/approvals/api/approvalApi';

export function useApprovalActions() {
  const queryClient = useQueryClient();

  const approveMutation = useMutation({
    mutationFn: approveRequest,
    onSuccess: async () => {
      // Pending list is the source shown on page, so we invalidate it after
      // every action to keep approval rows aligned with backend state.
      await queryClient.invalidateQueries({ queryKey: ['pending-approvals'] });
    },
  });

  const rejectMutation = useMutation({
    mutationFn: ({ requestId, reason }: { requestId: number; reason: string }) =>
      rejectRequest(requestId, { reason }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['pending-approvals'] });
    },
  });

  return {
    approveMutation,
    rejectMutation,
  };
}
