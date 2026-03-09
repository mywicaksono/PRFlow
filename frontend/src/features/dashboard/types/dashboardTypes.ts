export interface DashboardSummary {
  total_requests?: number;
  draft_requests?: number;
  submitted_requests?: number;
  approved_requests?: number;
  rejected_requests?: number;
  pending_approvals?: number;
  approved_by_me?: number;
  rejected_by_me?: number;
  total_submitted_requests?: number;
  total_approved_requests?: number;
  total_rejected_requests?: number;
  total_pending_approvals?: number;
}

export interface RecentDashboardRequest {
  id: number;
  request_number: string;
  status: string;
  amount: number;
  current_level: number;
  submitted_at: string | null;
  completed_at: string | null;
}
