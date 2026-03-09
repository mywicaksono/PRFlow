interface RequestPaginationProps {
  currentPage: number;
  lastPage: number;
  onPageChange: (page: number) => void;
}

export function RequestPagination({ currentPage, lastPage, onPageChange }: RequestPaginationProps) {
  if (lastPage <= 1) {
    return null;
  }

  return (
    <div className="mt-4 flex items-center justify-end gap-2">
      <button
        type="button"
        disabled={currentPage <= 1}
        onClick={() => onPageChange(currentPage - 1)}
        className="rounded-md border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40"
      >
        Prev
      </button>
      <span className="text-sm text-slate-600">
        Page {currentPage} / {lastPage}
      </span>
      <button
        type="button"
        disabled={currentPage >= lastPage}
        onClick={() => onPageChange(currentPage + 1)}
        className="rounded-md border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40"
      >
        Next
      </button>
    </div>
  );
}
