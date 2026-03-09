import { useNavigate } from 'react-router-dom';

import { useLogout } from '@/features/auth/hooks/useLogout';
import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser';

export function Topbar() {
  const navigate = useNavigate();
  const { user } = useCurrentUser();
  const { mutateAsync: logout, isPending } = useLogout();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <header className="border-b border-slate-200 bg-white px-6 py-4">
      <div className="flex items-center justify-between gap-4">
        <h1 className="text-lg font-semibold text-slate-800">PRFlow MVP</h1>
        <div className="flex items-center gap-3">
          <span className="text-sm text-slate-500">{user?.email ?? '-'}</span>
          <button
            type="button"
            onClick={handleLogout}
            disabled={isPending}
            className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {isPending ? 'Signing out...' : 'Logout'}
          </button>
        </div>
      </div>
    </header>
  );
}
