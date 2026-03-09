import { useQuery } from '@tanstack/react-query';

import { fetchDashboardSummary, fetchRecentDashboardRequests } from '@/features/dashboard/api/dashboardApi';

export function useDashboardSummary() {
  return useQuery({
    queryKey: ['dashboard-summary'],
    queryFn: fetchDashboardSummary,
  });
}

export function useRecentDashboardRequests() {
  return useQuery({
    queryKey: ['dashboard-recent-requests'],
    queryFn: fetchRecentDashboardRequests,
  });
}
