import { api } from '@/lib/api';
import type { ApiEnvelope } from '@/features/auth/types/authTypes';
import type { ListRequestsParams, PaginatedResponse, RequestItem } from '@/features/requests/types/requestTypes';

export async function fetchRequests(params: ListRequestsParams): Promise<PaginatedResponse<RequestItem>> {
  // Send only non-empty filters so backend validation receives clean query values.
  const queryParams = Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== ''),
  );

  const response = await api.get<ApiEnvelope<PaginatedResponse<RequestItem>>>('/requests', {
    params: queryParams,
  });

  return response.data.data;
}
