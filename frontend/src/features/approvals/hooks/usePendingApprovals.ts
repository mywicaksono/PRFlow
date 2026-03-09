import { useQuery } from '@tanstack/react-query';

import { fetchPendingApprovals } from '@/features/approvals/api/approvalApi';

export function usePendingApprovals(page: number) {
  return useQuery({
    queryKey: ['pending-approvals', page],
    queryFn: () => fetchPendingApprovals(page),
  });
}
