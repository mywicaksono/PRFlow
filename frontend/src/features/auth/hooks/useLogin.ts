import { useMutation } from '@tanstack/react-query';

import { useAuth } from '@/features/auth/context/AuthContext';
import type { LoginPayload } from '@/features/auth/types/authTypes';

export function useLogin() {
  const { login } = useAuth();

  return useMutation({
    mutationFn: async (payload: LoginPayload) => {
      await login(payload);
    },
  });
}
