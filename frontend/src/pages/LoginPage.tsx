import { Navigate } from 'react-router-dom';

import { LoginForm } from '@/features/auth/components/LoginForm';
import { useAuth } from '@/features/auth/context/AuthContext';

export function LoginPage() {
  const { isAuthenticated, isAuthLoading } = useAuth();

  if (!isAuthLoading && isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-100 px-4">
      <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 className="mb-2 text-2xl font-semibold text-slate-800">PRFlow Login</h1>
        <p className="mb-6 text-sm text-slate-600">Sign in to continue to your purchase workflow.</p>
        <LoginForm />
      </div>
    </div>
  );
}
