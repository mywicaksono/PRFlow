import type { PropsWithChildren } from 'react';
import { Navigate, useLocation } from 'react-router-dom';

import { useAuth } from '@/features/auth/context/AuthContext';

export function ProtectedRoute({ children }: PropsWithChildren) {
  const { isAuthenticated, isAuthLoading } = useAuth();
  const location = useLocation();

  // The route waits for session restore so users with valid tokens
  // are not redirected to login during initial bootstrap.
  if (isAuthLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-100 text-slate-600">
        Restoring session...
      </div>
    );
  }

  // Redirect happens only after restore completes to avoid navigation flicker
  // and preserve intended destination in router state.
  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return <>{children}</>;
}
