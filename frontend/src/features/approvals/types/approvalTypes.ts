import type { RequestItem } from '@/features/requests/types/requestTypes';

export interface PendingApprovalItem {
  id: number;
  request_id: number;
  approver_id: number;
  level: number;
  status: 'pending' | 'approved' | 'rejected';
  approved_at: string | null;
  notes: string | null;
  request?: RequestItem;
}

export interface RejectPayload {
  reason: string;
}
