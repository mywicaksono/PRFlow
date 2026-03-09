import { useAuth } from '@/features/auth/context/AuthContext';

export function useCurrentUser() {
  const { user, isAuthLoading } = useAuth();

  return {
    user,
    isAuthLoading,
  };
}
