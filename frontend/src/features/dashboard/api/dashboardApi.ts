import { api } from '@/lib/api';
import type { ApiEnvelope } from '@/features/auth/types/authTypes';
import type { DashboardSummary, RecentDashboardRequest } from '@/features/dashboard/types/dashboardTypes';

export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const response = await api.get<ApiEnvelope<DashboardSummary>>('/dashboard/summary');
  return response.data.data;
}

export async function fetchRecentDashboardRequests(): Promise<RecentDashboardRequest[]> {
  const response = await api.get<ApiEnvelope<RecentDashboardRequest[]>>('/dashboard/recent-requests');
  return response.data.data;
}
