import { useQuery } from '@tanstack/react-query';

import { fetchRequests } from '@/features/requests/api/requestApi';
import type { ListRequestsParams } from '@/features/requests/types/requestTypes';

export function useRequests(params: ListRequestsParams) {
  return useQuery({
    queryKey: ['requests', params],
    queryFn: () => fetchRequests(params),
  });
}
