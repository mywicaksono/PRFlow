import type { DashboardSummary } from '@/features/dashboard/types/dashboardTypes';

interface SummaryCardsProps {
  summary: DashboardSummary;
}

export function SummaryCards({ summary }: SummaryCardsProps) {
  const entries = Object.entries(summary);

  return (
    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      {entries.map(([key, value]) => (
        <div key={key} className="rounded-lg border border-slate-200 bg-white p-4">
          <p className="text-xs uppercase tracking-wide text-slate-500">{key.replaceAll('_', ' ')}</p>
          <p className="mt-2 text-2xl font-semibold text-slate-800">{value ?? 0}</p>
        </div>
      ))}
    </div>
  );
}
