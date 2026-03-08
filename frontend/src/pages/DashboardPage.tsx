import { AppLayout } from '@/components/layout/AppLayout';
import { RecentRequestsList } from '@/features/dashboard/components/RecentRequestsList';
import { SummaryCards } from '@/features/dashboard/components/SummaryCards';
import { useDashboardSummary, useRecentDashboardRequests } from '@/features/dashboard/hooks/useDashboardSummary';

export function DashboardPage() {
  const summaryQuery = useDashboardSummary();
  const recentRequestsQuery = useRecentDashboardRequests();

  return (
    <AppLayout>
      <div className="space-y-4">
        <h2 className="text-xl font-semibold text-slate-800">Dashboard</h2>

        {summaryQuery.isLoading ? <div className="rounded-lg border border-slate-200 bg-white p-6 text-slate-600">Loading summary...</div> : null}

        {summaryQuery.isError ? (
          <div className="rounded-lg border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">Failed to load summary.</div>
        ) : null}

        {summaryQuery.data ? <SummaryCards summary={summaryQuery.data} /> : null}

        <section className="space-y-2">
          <h3 className="text-lg font-semibold text-slate-800">Recent Requests</h3>

          {recentRequestsQuery.isLoading ? (
            <div className="rounded-lg border border-slate-200 bg-white p-6 text-slate-600">Loading recent requests...</div>
          ) : null}

          {recentRequestsQuery.isError ? (
            <div className="rounded-lg border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">
              Failed to load recent requests.
            </div>
          ) : null}

          {!recentRequestsQuery.isLoading && !recentRequestsQuery.isError && recentRequestsQuery.data?.length === 0 ? (
            <div className="rounded-lg border border-slate-200 bg-white p-6 text-slate-600">No recent requests available.</div>
          ) : null}

          {recentRequestsQuery.data && recentRequestsQuery.data.length > 0 ? (
            <RecentRequestsList items={recentRequestsQuery.data} />
          ) : null}
        </section>
      </div>
    </AppLayout>
  );
}
