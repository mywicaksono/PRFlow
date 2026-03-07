import { Link } from 'react-router-dom';

export function NotFoundPage() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-slate-100 px-4 text-center">
      <h1 className="text-3xl font-semibold text-slate-800">404</h1>
      <p className="text-slate-600">Halaman tidak ditemukan.</p>
      <Link to="/dashboard" className="rounded-md bg-slate-800 px-4 py-2 text-sm text-white">
        Kembali ke dashboard
      </Link>
    </div>
  );
}
