import { api } from '@/lib/api';
import type { ApiEnvelope } from '@/features/auth/types/authTypes';
import type { PendingApprovalItem, RejectPayload } from '@/features/approvals/types/approvalTypes';
import type { PaginatedResponse } from '@/features/requests/types/requestTypes';

export async function fetchPendingApprovals(page = 1): Promise<PaginatedResponse<PendingApprovalItem>> {
  const response = await api.get<ApiEnvelope<PaginatedResponse<PendingApprovalItem>>>('/approvals/pending', {
    params: { page },
  });

  return response.data.data;
}

export async function approveRequest(requestId: number): Promise<void> {
  await api.post(`/approvals/${requestId}/approve`);
}

export async function rejectRequest(requestId: number, payload: RejectPayload): Promise<void> {
  await api.post(`/approvals/${requestId}/reject`, payload);
}
